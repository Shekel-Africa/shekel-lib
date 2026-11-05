<?php

namespace Shekel\ShekelLib\ServiceAuth\Snapshot;

/**
 * A consumer's projection row (client_id, subject_id) -> {subject_revision, snapshot, jws,
 * last_verified_at} (spec §10.1). Persist every field; $snapshot is the decoded snapshot claim.
 */
class EligibilityProjection
{
    public function __construct(
        public string $clientId,
        public string $subjectId,
        public int $subjectRevision,
        public array $snapshot,
        public string $semanticDigest,
        public int $evaluatedAt,
        public int $lastVerifiedAt,
        public string $jws,
    ) {
    }

    public static function fromSnapshot(VerifiedSnapshot $snapshot): self
    {
        return new self(
            $snapshot->clientId,
            $snapshot->subjectId,
            $snapshot->subjectRevision,
            $snapshot->snapshot,
            $snapshot->semanticDigest,
            $snapshot->evaluatedAt,
            $snapshot->evaluatedAt,
            $snapshot->jws,
        );
    }

    /**
     * The snapshot entry for $action ({eligible, valid_until, reasons, basis}), or null.
     */
    public function action(string $action): ?array
    {
        $entry = $this->snapshot['actions'][$action] ?? null;

        return is_array($entry) ? $entry : null;
    }
}
