<?php

namespace Shekel\ShekelLib\Models;

use Illuminate\Database\Eloquent\Model;

class AccessToken extends Model
{

    protected $table = 'oauth_access_tokens';


    public static function isValid($id) {
        return self::where([
            ['id', $id],
            ['revoked', false]
        ])->exists();
    }
}
