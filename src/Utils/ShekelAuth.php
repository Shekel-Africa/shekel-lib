<?php

namespace Shekel\ShekelLib\Utils;

use Illuminate\Support\Facades\Session;
use Carbon\Carbon;
use Shekel\ShekelLib\Exceptions\ShekelInvalidArgumentException;
use Shekel\ShekelLib\Models\AccessToken;

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
     * @param string|null $token
     */
    public static function setXToken(string|null $token): void
    {
        Session::put(self::xTokenKey, $token);
    }

    /**
     * @param string $token
     * @param array $scopes
     * @return bool
     * @throws ShekelInvalidArgumentException
     */
    public static function verifyToken(string $token, array $scopes =[]): bool
    {
        if (empty($token)) {
            throw new ShekelInvalidArgumentException('Unauthenticated', 401);
        }
        $user = PassportToken::getUserFromToken($token);
        if (Carbon::now()->gt($user['expiry'])) {
            throw new ShekelInvalidArgumentException('Login Session Expired', 401);
        }
//        if (!AccessToken::isValid($user['token_id'])) {
//            throw new ShekelInvalidArgumentException('Login Session Token Revoked', 401);
//        }
        if (!empty($scopes)) {
            $scopes = collect($scopes);
            if (($scopes->diff($user['scopes']))->count() > 0) {
                throw new ShekelInvalidArgumentException('User does not have required permission', 403);
            }
        }
        return true;
    }


}