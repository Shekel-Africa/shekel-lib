<?php

namespace Shekel\ShekelLib\Utils;

use Illuminate\Support\Facades\Session;

class ShekelAuth
{
    const authKey = 'ShekelAuthToken';
    const xTokenKey = 'ShekelXToken';

    /**
     * @return string|null
     */
    public static function getAuthToken(): string|null
    {
        return Session::get(self::authKey);
    }

    /**
     * @param string $token
     */
    public static function setAuthToken(string $token): void
    {
        Session::put(self::authKey, $token);
    }
    /**
     * @return string|null
     */
    public static function getXToken(): string|null
    {
        return Session::get(self::xTokenKey);
    }

    /**
     * @param string $token
     */
    public static function setXToken(string $token): void
    {
        Session::put(self::xTokenKey, $token);
    }
}