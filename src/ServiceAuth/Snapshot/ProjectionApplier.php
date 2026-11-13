<?php

namespace Shekel\ShekelLib\ServiceAuth\Snapshot;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Shekel\ShekelLib\ServiceAuth\Events\EligibilitySnapshotConflict;
use Shekel\ShekelLib\ServiceAuth\Support\CacheLocks;
use Shekel\ShekelLib\ServiceAuth\Support\Clock;

/**
 * Applies a verified snapshot to the projection (spec §10.1), in one transaction through the
 * store, so the JWS, snapshot and last_verified_at change together:
 *
 * - no projection, or a higher revision: replace;
 * - equal revision: only with an identical semantic digest and an evaluated_at later than the
 *   stored one; that refreshes last_verified_at = evaluated_at. Different content at an equal
 *   revision is rejected and alerted (EligibilitySnapshotConflict);
 * - lower revision: ignored.
 *
 * A snapshot evaluated more than 60s in the future is rejected whatever its revision. A cache
 * lock per (client_id, subject_id) also serializes the first insert, which a row lock can't.
 */
class ProjectionApplier
{
    public const MAX_FUTURE_SKEW = 60;

    public function __construct(
        private EligibilityProjectionStore $store,
        private CacheRepository $cache,
        private Dispatcher $events,
    ) {
    }

    public function apply(VerifiedSnapshot $snapshot): ApplyResult
    {
        $lock = CacheLocks::lock($this->cache, 'shekel:service_auth:projection:'.sha1($snapshot->clientId.'|'.$snapshot->subjectId), 15);
        $lock->block(5);
        try {
            /** @var array{0: ApplyResult, 1: ?EligibilityProjection} $outcome */
            $outcome = $this->store->transaction(fn () => $this->applyLocked($snapshot));
        } finally {
            $lock->release();
        }
        [$result, $current] = $outcome;

        if ($result->outcome === ApplyResult::REJECTED_CONFLICT && $current !== null) {
            Log::error('Service auth: conflicting eligibility snapshots at the same revision.', [
                'client_id' => $snapshot->clientId,
                'subject_revision' => $snapshot->subjectRevision,
            ]);
            $this->events->dispatch(new EligibilitySnapshotConflict(
                $snapshot->clientId,
                $snapshot->subjectId,
                $snapshot->subjectRevision,
                $current->semanticDigest,
                $snapshot->semanticDigest,
            ));
        }

        return $result;
    }

    private function applyLocked(VerifiedSnapshot $snapshot): array
    {
        $current = $this->store->findForUpdate($snapshot->clientId, $snapshot->subjectId);

        if ($snapshot->evaluatedAt > Clock::now() + self::MAX_FUTURE_SKEW) {
            return [new ApplyResult(ApplyResult::REJECTED_FUTURE, $current), $current];
        }
        if ($current === null || $snapshot->subjectRevision > $current->subjectRevision) {
            $projection = EligibilityProjection::fromSnapshot($snapshot);
            $this->store->save($projection);

            return $this->confirm($snapshot, ApplyResult::APPLIED, $current);
        }
        if ($snapshot->subjectRevision < $current->subjectRevision) {
            return [new ApplyResult(ApplyResult::IGNORED_LOWER, $current), $current];
        }
        if (!hash_equals($current->semanticDigest, $snapshot->semanticDigest)) {
            return [new ApplyResult(ApplyResult::REJECTED_CONFLICT, $current), $current];
        }
        if ($snapshot->evaluatedAt <= $current->evaluatedAt) {
            return [new ApplyResult(ApplyResult::REJECTED_STALE, $current), $current];
        }

        $projection = EligibilityProjection::fromSnapshot($snapshot);
        $this->store->save($projection);

        return $this->confirm($snapshot, ApplyResult::REFRESHED, $current);
    }

    /**
     * Re-read after the conditional save: a concurrent first insert may have won, in which case
     * the outcome is classified against what is actually stored.
     */
    private function confirm(VerifiedSnapshot $snapshot, string $outcome, ?EligibilityProjection $current): array
    {
        $stored = $this->store->findForUpdate($snapshot->clientId, $snapshot->subjectId);
        if ($stored !== null && $stored->jws === $snapshot->jws) {
            return [new ApplyResult($outcome, $stored), $current];
        }
        if ($stored === null || $stored->subjectRevision > $snapshot->subjectRevision) {
            return [new ApplyResult(ApplyResult::IGNORED_LOWER, $stored), $stored];
        }
        if (!hash_equals($stored->semanticDigest, $snapshot->semanticDigest)) {
            return [new ApplyResult(ApplyResult::REJECTED_CONFLICT, $stored), $stored];
        }

        return [new ApplyResult(ApplyResult::REJECTED_STALE, $stored), $stored];
    }
}
