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
                'user_id' => $token_header_array['sub'],
                'scopes' => $token_header_array['scopes'],
                'expiry' => Carbon::createFromTimestamp($token_header_array['exp'])
            ];
        });

    }
}
