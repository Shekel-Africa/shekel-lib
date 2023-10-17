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
    public function generateReference(string $prefix, int $length=12, string $separator='-'): string
    {
        return $prefix.$separator.Str::random($length);
    }

    /**
     * Convert amount to float
     * @param int $amount
     * @return float
     */
    public function convertToNaira(int $amount):float {
        return round(floatVal($amount)/100, 2);
    }

    /**
     * Converts amount to Kobo
     * @param $amount
     * @return int
     */
    public function convertToKobo($amount):int {
        return intVal($amount * 100);
    }
}