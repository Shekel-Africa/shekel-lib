<?php

namespace Shekel\ShekelLib\ServiceAuth\Support;

use Illuminate\Support\Carbon;

/**
 * Current unix time through Carbon, so tests can freeze it with Carbon::setTestNow().
 */
final class Clock
{
    public static function now(): int
    {
        return Carbon::now()->getTimestamp();
    }
}
