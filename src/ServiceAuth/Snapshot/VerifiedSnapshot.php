<?php

namespace Shekel\ShekelLib\ServiceAuth\Snapshot;

/**
 * A verified eligibility snapshot (spec §10.1). $jws is the compact JWS to store with the
 * projection, and on any loan authorized from it.
 */
class VerifiedSnapshot
{
    public function __construct(
        public string $jws,
        public string $kid,
        public string $clientId,
        public string $subjectId,
        public int $subjectRevision,
        public int $evaluatedAt,
        public int $issuedAt,
        public int $expiresAt,
        public ?string $jti,
        public array $snapshot,
        public string $semanticDigest,
    ) {
    }
}
