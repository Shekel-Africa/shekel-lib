<?php

namespace Shekel\ShekelLib\ServiceAuth\Consumer;

use InvalidArgumentException;
use Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjection;
use Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjectionStore;
use Shekel\ShekelLib\ServiceAuth\Support\Clock;

/**
 * The §10.2 outage contract, shared by every guarding service:
 *
 * | Projection                                      | signature-api reachable    | signature-api down                  |
 * |-------------------------------------------------|----------------------------|-------------------------------------|
 * | valid, eligible                                 | allow (no call)            | allow                               |
 * | valid, not eligible                             | deny 409 (no call)         | new credit: deny; servicing: allow  |
 * | expired (now >= valid_until) or stale (>= 24h)  | fetch synchronously, store | new credit: deny; servicing: allow  |
 * | absent                                          | fetch synchronously        | new credit: deny; servicing: allow  |
 *
 * "Valid" = present, valid_until null or in the future, and last_verified_at under 24h old. An
 * action missing from the snapshot counts as not eligible. "Down" for the no-call rows means a
 * fetch failed in the last minute (SnapshotFetcher::recentlyUnavailable()); for the fetch rows it
 * means this fetch failed (unreachable, 5xx, rejected snapshot or 404), or still left no valid
 * projection.
 */
class EligibilityGuard
{
    public const NEW_CREDIT = 'new_credit';
    public const SERVICING = 'servicing';

    public const STATE_VALID_ELIGIBLE = 'valid_eligible';
    public const STATE_VALID_INELIGIBLE = 'valid_ineligible';
    public const STATE_EXPIRED = 'expired_or_stale';
    public const STATE_ABSENT = 'absent';

    public const MAX_AGE = 86400;

    public function __construct(
        private EligibilityProjectionStore $store,
        private SnapshotFetcher $fetcher,
    ) {
    }

    public function check(string $clientId, string $subjectId, string $action, string $class): GuardDecision
    {
        self::assertClass($class);

        $projection = $this->store->find($clientId, $subjectId);
        $state = self::state($projection, $action, Clock::now());
        $reachable = !$this->fetcher->recentlyUnavailable();
        $outcome = self::matrix($state, $reachable, $class);
        if ($outcome !== GuardDecision::FETCH) {
            return $this->decision($outcome, $state, $action, $class, $projection, false, $reachable);
        }

        $result = $this->fetcher->fetch($clientId, $subjectId);
        if ($result->verified()) {
            $fresh = $this->store->find($clientId, $subjectId);
            $freshState = self::state($fresh, $action, Clock::now());
            if (in_array($freshState, [self::STATE_VALID_ELIGIBLE, self::STATE_VALID_INELIGIBLE], true)) {
                return $this->decision(self::matrix($freshState, true, $class), $freshState, $action, $class, $fresh, true, true);
            }
        }

        // No valid projection after the fetch: the "signature-api down" column.
        return $this->decision(self::matrix($state, false, $class), $state, $action, $class, $projection, true, false);
    }

    /**
     * Which §10.2 row a projection is in for $action at $now.
     */
    public static function state(?EligibilityProjection $projection, string $action, int $now): string
    {
        if ($projection === null) {
            return self::STATE_ABSENT;
        }
        if ($now - $projection->lastVerifiedAt >= self::MAX_AGE) {
            return self::STATE_EXPIRED;
        }
        $entry = $projection->action($action);
        if ($entry === null) {
            return self::STATE_VALID_INELIGIBLE;
        }
        $validUntil = $entry['valid_until'] ?? null;
        if ($validUntil !== null && (!is_numeric($validUntil) || $now >= (int) $validUntil)) {
            return self::STATE_EXPIRED;
        }

        return ($entry['eligible'] ?? false) === true ? self::STATE_VALID_ELIGIBLE : self::STATE_VALID_INELIGIBLE;
    }

    /**
     * The §10.2 cell: allow, deny or fetch.
     */
    public static function matrix(string $state, bool $reachable, string $class): string
    {
        self::assertClass($class);
        if ($state === self::STATE_VALID_ELIGIBLE) {
            return GuardDecision::ALLOW;
        }
        if (!in_array($state, [self::STATE_VALID_INELIGIBLE, self::STATE_EXPIRED, self::STATE_ABSENT], true)) {
            throw new InvalidArgumentException("Unknown projection state '$state'.");
        }
        if ($reachable) {
            return $state === self::STATE_VALID_INELIGIBLE ? GuardDecision::DENY : GuardDecision::FETCH;
        }

        return $class === self::SERVICING ? GuardDecision::ALLOW : GuardDecision::DENY;
    }

    private static function assertClass(string $class): void
    {
        if (!in_array($class, [self::NEW_CREDIT, self::SERVICING], true)) {
            throw new InvalidArgumentException("Unknown guard class '$class'.");
        }
    }

    private function decision(string $outcome, string $state, string $action, string $class, ?EligibilityProjection $projection, bool $fetched, bool $reachable): GuardDecision
    {
        $valid = in_array($state, [self::STATE_VALID_ELIGIBLE, self::STATE_VALID_INELIGIBLE], true);
        $entry = $projection?->action($action);
        $reasons = $entry !== null && is_array($entry['reasons'] ?? null) ? $entry['reasons'] : [];
        if ($entry === null && $state === self::STATE_VALID_INELIGIBLE) {
            $reasons = ['action_not_in_snapshot'];
        }
        if (!$valid && $outcome === GuardDecision::DENY) {
            $reasons = ['eligibility_unavailable'];
        }

        return new GuardDecision(
            $outcome,
            $state,
            $action,
            $class,
            $reasons,
            $outcome === GuardDecision::ALLOW && $state === self::STATE_VALID_ELIGIBLE ? $projection?->jws : null,
            $fetched,
            $reachable,
        );
    }
}
