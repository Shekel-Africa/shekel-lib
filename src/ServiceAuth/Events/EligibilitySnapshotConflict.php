<?php

namespace Shekel\ShekelLib\ServiceAuth\Events;

/**
 * Alert: signature-api signed two different snapshots with the same subject_revision. The stored
 * projection is kept. Listen to it and page.
 */
class EligibilitySnapshotConflict
{
    public function __construct(
        public string $clientId,
        public string $subjectId,
        public int $subjectRevision,
        public string $storedDigest,
        public string $receivedDigest,
    ) {
    }
}
