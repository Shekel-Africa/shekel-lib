<?php

namespace Shekel\ShekelLib\Utils;

use Illuminate\Support\Str;

class Utils
{
    /**
     * Generate Reference Code
     * @param string $prefix
     * @param int $length
     * @param string $separator
     * @return string
     */
    public static function generateReference(string $prefix, int $length=12, string $separator='-'): string
    {
        return $prefix.$separator.Str::random($length);
    }

    /**
     * Convert amount to float
     * @param int $amount
     * @return float
     */
    public static function convertToNaira(int $amount):float {
        return round(floatVal($amount)/100, 2);
    }

    /**
     * Converts amount to Kobo
     * @param $amount
     * @return int
     */
    public static function convertToKobo($amount):int {
        return round(($amount * 100), 0);
    }
}