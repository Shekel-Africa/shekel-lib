<?php

namespace Shekel\ShekelLib\ServiceAuth\Events;

/**
 * Alert: a fetched eligibility snapshot failed verification (bad signature, wrong aud/sub/
 * client_id, expired, ...) and was discarded. $reason is a short code.
 */
class EligibilitySnapshotRejected
{
    public function __construct(
        public string $clientId,
        public string $subjectId,
        public string $reason,
    ) {
    }
}
