<?php

namespace Shekel\ShekelLib\Utils;

use Illuminate\Support\Str;
use Shekel\ShekelLib\Exceptions\ShekelInvalidArgumentException;
use Symfony\Component\Intl\Currencies;

class Utils
{
    /**
     * Generate Reference Code
     * @param string $prefix
     * @param int $length
     * @param bool $useClient
     * @param string $separator
     * @param string $clientSeparator
     * @return string
     */
    public static function generateReference(string $prefix, int $length = 12, bool $useClient = false, string $separator = '-',  string $clientSeparator = '^'): string
    {
        if ($useClient && isset(TenantClient::getClientObject()?->tp_reference)) {
            $prefix = TenantClient::getClientObject()?->tp_reference . $clientSeparator . $prefix;
        }
        return $prefix . $separator . Str::random($length);
    }

    /**
     * @param $reference
     * @return string|null
     */
    public static function getClientFromReference($reference): ?string
    {
        $separator = match (Str::contains($reference, '.')) {
            true => '.',
            default => '^',
        };
        $ref = explode($separator, $reference);
        if (count($ref) == 1) {
            return null;
        }
        return $ref[0];
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

    public static function isValidId($id): bool {
        if (is_string($id) && !Str::isUuid($id)) {
            throw new ShekelInvalidArgumentException('Invalid Id', 400);
        }
        return true;
    }

    function money_format($amount, $currency_code): string
    {
        $currency = match ($currency_code) {
            'NGN' => '₦',
            default => Currencies::getSymbol($currency_code),
        };
        return $currency . number_format($amount, 2);
    }
}