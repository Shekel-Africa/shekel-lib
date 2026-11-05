<?php

namespace Shekel\ShekelLib\Utils;

use Illuminate\Http\Request;

class CustomRateLimiter
{
    public static function getRateLimitKey(Request $request)
    {
        $by = $request->user()?->id ?: $request->ip();
        if (!empty($request->bearerToken())) {
            if (env('APP_ENV') !== 'testing') {
                // An unverifiable token is limited by IP, like an anonymous request.
                $user = PassportToken::findUserFromToken($request->bearerToken());
                if ($user !== null) {
                    $by = sha1($user['user_id'] . $request->ip());
                }
            }
        }
        return $by;
    }
}