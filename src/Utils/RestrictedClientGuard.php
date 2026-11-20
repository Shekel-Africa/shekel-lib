<?php

namespace Shekel\ShekelLib\Utils;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Shekel\ShekelLib\Middleware\CheckAnyServiceAuthentication;
use Shekel\ShekelLib\Middleware\ServiceAuthentication;
use Shekel\ShekelLib\Middleware\v3\ServiceAuthentication as V3ServiceAuthentication;
use Throwable;

/**
 * Keeps tokens of restricted (third-party) OAuth clients off routes that declare no scope (S20-4896).
 *
 * A third-party client such as the Shailoq dealer assistant may only use the scopes its user
 * consented to. A route guarded by sAuth / v3.sAuth alone asks authentication-api "who is this?",
 * which needs no scope, so without this any valid token reached it: purchases, swaps, marketplace
 * offers. Here such a token is refused with 403 unless the route declares a scope, in which case
 * the scope middleware decides as before.
 *
 * Restricted means: the guard is on (config shekel.restricted_clients.enabled, default true) and the
 * token's `aud` is in shekel.restricted_clients.client_ids, or the token carries
 * client_kind=third_party. Every other token, and every request without one, is untouched.
 *
 * The claims are read from the token without checking its signature. Callers only ask after the
 * token has been verified: by authentication-api (sAuth) or against the JWKS (the s2sAuth
 * middlewares). A forged payload cannot help either way: it fails that verification.
 */
class RestrictedClientGuard
{
    public const CONFIG_KEY = 'shekel.restricted_clients';
    public const MESSAGE = 'This client may not use this endpoint.';

    /** shekel-lib and Passport middleware that enforce a scope when given one. */
    private const SCOPE_MIDDLEWARE = [
        ServiceAuthentication::class,
        V3ServiceAuthentication::class,
        CheckAnyServiceAuthentication::class,
        'Laravel\Passport\Http\Middleware\CheckScopes',
        'Laravel\Passport\Http\Middleware\CheckForAnyScope',
        'Laravel\Passport\Http\Middleware\CheckToken',
        'Laravel\Passport\Http\Middleware\CheckTokenForAnyScope',
    ];

    public static function enabled(): bool
    {
        $enabled = config(self::CONFIG_KEY.'.enabled', true);
        if ($enabled === null || $enabled === '') {
            return true;
        }
        if (is_bool($enabled)) {
            return $enabled;
        }

        return filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    /** @return string[] */
    public static function clientIds(): array
    {
        $ids = config(self::CONFIG_KEY.'.client_ids', []);
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        return array_values(array_filter(array_map(fn ($id) => trim((string) $id), (array) $ids), fn ($id) => $id !== ''));
    }

    /**
     * The 403 for a restricted token on a route without a scope, or null to carry on.
     *
     * @param string[] $scopes the scopes the calling middleware was given, if it takes any
     */
    public static function check(Request $request, array $scopes = []): ?JsonResponse
    {
        if (!self::refuses($request, $scopes)) {
            return null;
        }

        return response()->json(['message' => self::MESSAGE], 403);
    }

    /**
     * @param string[] $scopes the scopes the calling middleware was given, if it takes any
     */
    public static function refuses(Request $request, array $scopes = []): bool
    {
        if (!self::enabled() || self::hasScope($scopes)) {
            return false;
        }

        $claims = self::claims($request->bearerToken());
        if ($claims === null || !self::isRestricted($claims)) {
            return false;
        }

        if (self::routeDeclaresScope($request)) {
            return false;
        }

        // Ids only: the client and the path, never the token or the user's details.
        Log::info('restricted_client.refused', [
            'client_id' => self::audiences($claims)[0] ?? null,
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        return true;
    }

    /** Whether verified token claims belong to a restricted client. */
    public static function isRestricted(array $claims): bool
    {
        if (!self::enabled()) {
            return false;
        }

        $claim = (string) config(self::CONFIG_KEY.'.claim', 'client_kind');
        $value = (string) config(self::CONFIG_KEY.'.claim_value', 'third_party');
        if ($claim !== '' && isset($claims[$claim]) && $claims[$claim] === $value) {
            return true;
        }

        $restricted = self::clientIds();

        return $restricted !== [] && array_intersect(self::audiences($claims), $restricted) !== [];
    }

    /**
     * Whether any middleware on the request's route enforces a scope: an s2sAuth-style or Passport
     * scope middleware given at least one scope. Groups and aliases are resolved by the router.
     */
    public static function routeDeclaresScope(Request $request): bool
    {
        $route = $request->route();
        if (!$route instanceof Route) {
            return false;
        }

        try {
            $middleware = app('router')->gatherRouteMiddleware($route);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $scoped = array_map(
            fn ($class) => ltrim((string) $class, '\\'),
            array_merge(self::SCOPE_MIDDLEWARE, (array) config(self::CONFIG_KEY.'.scope_middleware', []))
        );

        foreach ($middleware as $entry) {
            if (!is_string($entry) || !str_contains($entry, ':')) {
                continue;
            }
            [$name, $parameters] = explode(':', $entry, 2);
            if (in_array(ltrim($name, '\\'), $scoped, true) && self::hasScope(explode(',', $parameters))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The token's payload, unverified (see the class comment), or null if it is not a JWT.
     */
    public static function claims(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) {
            return null;
        }
        $claims = json_decode($payload, true);

        return is_array($claims) ? $claims : null;
    }

    /** @return string[] */
    private static function audiences(array $claims): array
    {
        $aud = $claims['aud'] ?? [];

        return array_values(array_filter(array_map(
            fn ($value) => is_scalar($value) ? (string) $value : '',
            is_array($aud) ? $aud : [$aud]
        ), fn ($value) => $value !== ''));
    }

    private static function hasScope(array $scopes): bool
    {
        foreach ($scopes as $scope) {
            if (is_string($scope) && trim($scope) !== '') {
                return true;
            }
        }

        return false;
    }
}
