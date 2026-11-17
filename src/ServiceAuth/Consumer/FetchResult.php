<?php

namespace Shekel\ShekelLib\ServiceAuth\Consumer;

use Shekel\ShekelLib\ServiceAuth\Snapshot\ApplyResult;

/**
 * Outcome of one SnapshotFetcher::fetch().
 */
class FetchResult
{
    /** A verified snapshot was handed to the applier ($apply says what it did). */
    public const VERIFIED = 'verified';
    /** signature-api answered 404: unknown or cross-tenant subject, no snapshot. */
    public const NOT_FOUND = 'not_found';
    /** The snapshot failed verification; discarded and alerted. */
    public const REJECTED = 'rejected';
    /** signature-api (or the OP) could not be reached or failed. */
    public const UNAVAILABLE = 'unavailable';

    public function __construct(public string $status, public ?ApplyResult $apply = null, public ?string $reason = null)
    {
    }

    public function verified(): bool
    {
        return $this->status === self::VERIFIED;
    }
}
