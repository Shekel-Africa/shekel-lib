<?php

namespace Shekel\ShekelLib\ServiceAuth\Consumer;

/**
 * What HintReceiver decided for one hint.
 */
class HintDecision
{
    /** A fetch job was queued. */
    public const QUEUED = 'queued';
    /** A fetch was needed but one is already pending for this subject. */
    public const COALESCED = 'coalesced';
    /** Over the tenant's fetch budget: dropped (the pull sweep repairs it). */
    public const RATE_LIMITED = 'rate_limited';
    /** The projection is current; nothing to do. */
    public const SKIPPED = 'skipped';

    public function __construct(public string $outcome, public string $reason)
    {
    }
}
