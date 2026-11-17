<?php

namespace Shekel\ShekelLib\ServiceAuth\Snapshot;

use RuntimeException;

/**
 * An eligibility snapshot failed verification and must be discarded (and alerted). The message
 * is a short reason code.
 */
class SnapshotVerificationException extends RuntimeException
{
}
