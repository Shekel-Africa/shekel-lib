<?php

namespace Shekel\ShekelLib\Utils;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class PassportToken
{
    public static function getUserFromToken($token) {
        return Cache::remember($token, '14440', function()use ($token) {
            $token_parts = explode('.', $token);
            $token_header_json = base64_decode($token_parts[1]);
            $token_header_array = json_decode($token_header_json, true);
            return [
                'token_id' => $token_header_array['jti'],
                'client_id' => $token_header_array['aud'],
                'user_id' => $token_header_array['sub'],
                'scopes' => $token_header_array['scopes'],
                'expiry' => Carbon::createFromTimestamp($token_header_array['exp'])
            ];
        });
    }

    public static function getClientDetailFromRequest($request) {
        if (!$request->hasHeader('x-auth')) {
            abort(400, "Client Secrets Missing");
        }
        $token = $request->header('x-auth');
        return Cache::remember($token, '144404', function () use($token) {
            $arr = explode(':', base64_decode($token));
            if (count($arr) !== 2) {
                abort(400, "Invalid client secrets");
            }
            return [
                'client_id' => $arr[0],
                'client_secret' => $arr[1]
            ];
        });
    }

    public static function validateUserRoleForAction(object $user, string $requiredRole) {
        if(!isset($user->user_type) || $user->user_type !== $requiredRole) {
            abort("User does not have required permission");
        }
        return true;
    }
}
