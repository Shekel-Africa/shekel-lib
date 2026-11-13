<?php

namespace Shekel\ShekelLib\ServiceAuth\Snapshot;

/**
 * What ProjectionApplier did with a verified snapshot.
 */
class ApplyResult
{
    /** No projection yet, or a higher revision: replaced. */
    public const APPLIED = 'applied';
    /** Equal revision, identical digest, later evaluated_at: last_verified_at refreshed. */
    public const REFRESHED = 'refreshed';
    /** Lower revision: ignored. */
    public const IGNORED_LOWER = 'ignored_lower';
    /** Equal revision with an evaluated_at not later than the stored one: rejected. */
    public const REJECTED_STALE = 'rejected_stale';
    /** evaluated_at more than 60s in the future: rejected. */
    public const REJECTED_FUTURE = 'rejected_future';
    /** Equal revision with different content: rejected and alerted. */
    public const REJECTED_CONFLICT = 'rejected_conflict';

    public function __construct(public string $outcome, public ?EligibilityProjection $projection)
    {
    }

    public function changed(): bool
    {
        return in_array($this->outcome, [self::APPLIED, self::REFRESHED], true);
    }
}
