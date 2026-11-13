<?php

namespace Shekel\ShekelLib\ServiceAuth;

use Illuminate\Support\Facades\Config;

/**
 * Reads config('shekel.service_auth.*'). Defaults live in resources/config/shekel-service-auth.php.
 */
final class ServiceAuthConfig
{
    public const KEY = 'shekel.service_auth';

    public static function get(string $key, $default = null)
    {
        return Config::get(self::KEY.'.'.$key, $default);
    }

    public static function string(string $key): string
    {
        $value = self::get($key);

        return is_string($value) ? trim($value) : '';
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);

        return is_numeric($value) ? (int) $value : $default;
    }
}
