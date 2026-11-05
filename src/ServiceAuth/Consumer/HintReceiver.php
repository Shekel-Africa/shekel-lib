<?php

namespace Shekel\ShekelLib\ServiceAuth\Consumer;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shekel\ShekelLib\ServiceAuth\Middleware\OAuthServiceTokenAuthentication;
use Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjectionStore;
use Shekel\ShekelLib\ServiceAuth\Support\Clock;

/**
 * Handles signature-api's data-free hints (spec §10.1). A hint never changes the projection; it
 * can only queue a fetch of the signed snapshot. Answers 202.
 *
 * A fetch is queued for a `change` hint with a higher revision than the projection's, for any
 * hint when there is no projection, and for a `refresh` hint when last_verified_at is more than
 * 12h old. Fetches are coalesced per (client_id, subject_id): at most one pending, and at most one
 * started per min-fetch-interval (enforced atomically when the job starts; an early job is released
 * back to the queue). Across subjects, at most $tenantFetchesPerMinute fetches are queued per
 * tenant per minute; hints over that budget are dropped and the pull sweep repairs them.
 *
 * Use a queue connection that supports delays (not "sync") for the fetch job.
 *
 * Route (consumer side):
 *     Route::post('/v3/internal/signatures/hint', fn (Request $r, HintReceiver $h) => $h->receive($r))
 *         ->middleware('service.oauth:loan-api:signature.hint,signature-api');
 */
class HintReceiver
{
    public function __construct(
        private EligibilityProjectionStore $store,
        private CacheRepository $cache,
        private BusDispatcher $bus,
        private int $refreshAfter = 43200,
        private int $minFetchInterval = 10,
        private int $pendingTtl = 120,
        private int $tenantFetchesPerMinute = 300,
        private ?string $queueConnection = null,
        private ?string $queue = null,
    ) {
    }

    public function receive(Request $request): JsonResponse
    {
        $clientId = $request->input('client_id');
        $subjectId = $request->input('subject_id');
        $revision = $request->input('subject_revision');
        $purpose = $request->input('purpose', 'change');

        $errors = [];
        if (!is_string($clientId) || $clientId === '') {
            $errors['client_id'] = 'required';
        }
        if (!is_string($subjectId) || !preg_match('/^[A-Za-z0-9._:\-]{1,128}$/', $subjectId)) {
            $errors['subject_id'] = 'invalid';
        }
        if (!is_int($revision) && !(is_string($revision) && ctype_digit($revision))) {
            $errors['subject_revision'] = 'invalid';
        }
        if (!in_array($purpose, ['change', 'refresh'], true)) {
            $errors['purpose'] = 'invalid';
        }
        if ($errors !== []) {
            return new JsonResponse(['message' => 'Invalid hint.', 'errors' => $errors], 422);
        }

        $tenantId = $request->attributes->get(OAuthServiceTokenAuthentication::ATTR_TENANT_ID);
        if (!is_string($tenantId) || !hash_equals($tenantId, $clientId)) {
            return new JsonResponse(['message' => 'Forbidden.'], 403);
        }

        $this->handle($clientId, $subjectId, (int) $revision, $purpose);

        return new JsonResponse(['message' => 'Accepted.'], 202);
    }

    public function handle(string $clientId, string $subjectId, int $revision, string $purpose): HintDecision
    {
        $projection = $this->store->find($clientId, $subjectId);
        if ($projection === null) {
            $reason = 'no_projection';
        } elseif ($purpose === 'change' && $revision > $projection->subjectRevision) {
            $reason = 'higher_revision';
        } elseif ($purpose === 'refresh' && Clock::now() - $projection->lastVerifiedAt > $this->refreshAfter) {
            $reason = 'refresh_due';
        } else {
            return new HintDecision(HintDecision::SKIPPED, 'current');
        }

        // The pending slot is owned by the job that holds its token.
        $token = bin2hex(random_bytes(8));
        if (!$this->cache->add($this->key('pending', $clientId, $subjectId), $token, $this->pendingTtl)) {
            return new HintDecision(HintDecision::COALESCED, $reason);
        }
        if (!$this->withinTenantBudget($clientId)) {
            $this->clearPending($clientId, $subjectId, $token);
            Log::warning('Service auth: hint fetch budget exceeded; hint dropped.', ['client_id' => $clientId]);

            return new HintDecision(HintDecision::RATE_LIMITED, $reason);
        }

        $job = new FetchEligibilitySnapshot($clientId, $subjectId, $token);
        $last = $this->cache->get($this->key('last', $clientId, $subjectId));
        $delay = is_int($last) ? $last + $this->minFetchInterval - Clock::now() : 0;
        if ($delay > 0) {
            $job->delay($delay);
        }
        if ($this->queueConnection !== null && $this->queueConnection !== '') {
            $job->onConnection($this->queueConnection);
        }
        if ($this->queue !== null && $this->queue !== '') {
            $job->onQueue($this->queue);
        }
        $this->bus->dispatch($job);

        return new HintDecision(HintDecision::QUEUED, $reason);
    }

    /**
     * Called by the job before it fetches. Returns 0 when the fetch may start now (the pending
     * slot is freed and the interval starts, atomically), or the seconds to wait otherwise.
     */
    public function fetchStarting(string $clientId, string $subjectId, ?string $token = null): int
    {
        $now = Clock::now();
        if (!$this->cache->add($this->key('last', $clientId, $subjectId), $now, max($this->minFetchInterval, 1))) {
            $last = $this->cache->get($this->key('last', $clientId, $subjectId));

            return max(1, (is_int($last) ? $last + $this->minFetchInterval - $now : $this->minFetchInterval));
        }
        $this->clearPending($clientId, $subjectId, $token);

        return 0;
    }

    /**
     * Free the pending slot if $token still owns it (null: whoever owns it).
     */
    public function clearPending(string $clientId, string $subjectId, ?string $token = null): void
    {
        $key = $this->key('pending', $clientId, $subjectId);
        if ($token === null || $this->cache->get($key) === $token) {
            $this->cache->forget($key);
        }
    }

    private function withinTenantBudget(string $clientId): bool
    {
        $key = 'shekel:service_auth:hint:budget:'.sha1($clientId).':'.intdiv(Clock::now(), 60);
        $this->cache->add($key, 0, 120);

        $count = $this->cache->increment($key);

        return $count !== false && (int) $count <= $this->tenantFetchesPerMinute;
    }

    private function key(string $kind, string $clientId, string $subjectId): string
    {
        return "shekel:service_auth:hint:$kind:".sha1($clientId.'|'.$subjectId);
    }
}
