<?php

namespace Shekel\ShekelLib\ServiceAuth\Consumer;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Shekel\ShekelLib\ServiceAuth\Client\ServiceTokenProvider;
use Shekel\ShekelLib\ServiceAuth\Events\EligibilitySnapshotRejected;
use Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilitySnapshotVerifier;
use Shekel\ShekelLib\ServiceAuth\Snapshot\ProjectionApplier;
use Shekel\ShekelLib\ServiceAuth\Snapshot\SnapshotVerificationException;
use Throwable;

/**
 * Pulls a signed eligibility snapshot (spec §10.1): GET {signature-api}/v1/internal/eligibility/
 * {subject_id} with this service's own token (scope signature-api:eligibility.read, tenant =
 * client_id), verifies it, then applies it to the projection.
 *
 * The response body is either the compact JWS (Content-Type application/jwt) or JSON with the
 * JWS at "jws" or "data.jws".
 *
 * A transport failure or 5xx marks signature-api unavailable for a minute; EligibilityGuard reads
 * that for the §10.2 "signature-api down" column.
 */
class SnapshotFetcher
{
    public const SCOPE = 'signature-api:eligibility.read';
    private const UNAVAILABLE_KEY = 'shekel:service_auth:signature_api_unavailable';
    private const UNAVAILABLE_TTL = 60;

    public function __construct(
        private ServiceTokenProvider $tokens,
        private HttpFactory $http,
        private EligibilitySnapshotVerifier $verifier,
        private ProjectionApplier $applier,
        private CacheRepository $cache,
        private Dispatcher $events,
        private string $baseUrl,
        private int $timeout = 5,
    ) {
    }

    public function fetch(string $clientId, string $subjectId): FetchResult
    {
        if ($this->baseUrl === '') {
            throw new \LogicException('shekel.service_auth.signature_api_url is not configured.');
        }
        $url = rtrim($this->baseUrl, '/').'/v1/internal/eligibility/'.rawurlencode($subjectId);

        try {
            $response = $this->tokens->withRetryOn401([self::SCOPE], $clientId, fn (string $token) => $this->http
                ->withToken($token)
                ->accept('application/jwt, application/json')
                ->timeout($this->timeout)
                ->connectTimeout(min($this->timeout, 2))
                ->withOptions(['allow_redirects' => false])
                ->get($url));
        } catch (Throwable $e) {
            return $this->unavailable($clientId, 'unreachable: '.class_basename($e));
        }

        if ($response->status() === 404) {
            return new FetchResult(FetchResult::NOT_FOUND);
        }
        if (!$response->successful()) {
            return $this->unavailable($clientId, 'status '.$response->status());
        }
        $this->cache->forget(self::UNAVAILABLE_KEY);

        try {
            $snapshot = $this->verifier->verify($this->extractJws($response), $clientId, $subjectId);
        } catch (SnapshotVerificationException $e) {
            Log::error('Service auth: eligibility snapshot rejected.', ['client_id' => $clientId, 'reason' => $e->getMessage()]);
            $this->events->dispatch(new EligibilitySnapshotRejected($clientId, $subjectId, $e->getMessage()));

            return new FetchResult(FetchResult::REJECTED, null, $e->getMessage());
        }

        return new FetchResult(FetchResult::VERIFIED, $this->applier->apply($snapshot));
    }

    /**
     * Whether a fetch failed on transport/5xx within the last minute.
     */
    public function recentlyUnavailable(): bool
    {
        return (bool) $this->cache->get(self::UNAVAILABLE_KEY, false);
    }

    private function extractJws($response): string
    {
        $contentType = strtolower((string) $response->header('Content-Type'));
        if (str_starts_with($contentType, 'application/jwt')) {
            return trim($response->body());
        }
        $jws = $response->json('jws') ?? $response->json('data.jws');

        return is_string($jws) ? $jws : '';
    }

    private function unavailable(string $clientId, string $reason): FetchResult
    {
        Log::warning('Service auth: eligibility fetch failed.', ['client_id' => $clientId, 'reason' => $reason]);
        $this->cache->put(self::UNAVAILABLE_KEY, true, self::UNAVAILABLE_TTL);

        return new FetchResult(FetchResult::UNAVAILABLE, null, $reason);
    }
}
