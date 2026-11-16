<?php

namespace Shekel\ShekelLib\Utils;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Shekel\ShekelLib\Exceptions\ShekelInvalidArgumentException;
use Shekel\ShekelLib\ServiceAuth\ServiceAuthConfigurationException;
use Shekel\ShekelLib\ServiceAuth\Token\AccessTokenVerifier;

class PassportToken
{
    /**
     * The user behind an access token, read only after its signature and lifetime have been
     * verified against the OP JWKS (config shekel.service_auth.jwks_url, env SERVICE_AUTH_JWKS_URL).
     *
     * @return array{token_id: ?string, client_id: mixed, user_id: string, scopes: string[], expiry: Carbon}
     *
     * @throws ShekelInvalidArgumentException (401) when the token cannot be verified
     * @throws ServiceAuthConfigurationException when the JWKS URL is not configured
     */
    public static function getUserFromToken($token) {
        $user = self::findUserFromToken($token);
        if ($user === null) {
            throw new ShekelInvalidArgumentException('Unauthenticated', 401);
        }

        return $user;
    }

    /**
     * As getUserFromToken, but null when the token cannot be verified.
     *
     * @throws ServiceAuthConfigurationException when the JWKS URL is not configured
     */
    public static function findUserFromToken($token): ?array {
        $claims = app(AccessTokenVerifier::class)->verify(is_string($token) ? $token : null);
        if ($claims === null || !isset($claims['sub']) || !is_string($claims['sub'])) {
            return null;
        }
        $scopes = $claims['scopes'] ?? [];
        if (!is_array($scopes) || array_filter($scopes, fn ($scope) => !is_string($scope)) !== []) {
            return null;
        }

        return [
            'token_id' => isset($claims['jti']) && is_string($claims['jti']) ? $claims['jti'] : null,
            'client_id' => $claims['aud'] ?? null,
            'user_id' => $claims['sub'],
            'scopes' => array_values($scopes),
            'expiry' => Carbon::createFromTimestamp((int) $claims['exp']),
        ];
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

    public static function validateUserRoleForAction(object $user, $requiredRole): bool
    {
        if (!is_array($requiredRole)) {
            $requiredRole = [$requiredRole];
        }
        if(!isset($user->user_type) || !in_array($user->user_type, $requiredRole)) {
            abort(403, "User does not have required permission");
        }
        return true;
    }
}
