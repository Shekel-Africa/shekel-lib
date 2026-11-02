<?php

namespace Shekel\ShekelLib\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Shekel\ShekelLib\Utils\PassportToken;

/**
 * Lets a request in with the calling service's secret (the s2s and s2sName headers), or with a
 * user an earlier middleware signed in, holding one of $roles when any are given. Anything else
 * is refused with 401: a wrong or missing secret no longer falls through, and a missing user is
 * no longer handed to the role check, where it failed with a TypeError (500).
 */
class ShekelServiceRoleProtectionMiddleware
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $serviceSecret = $request->header('s2s');
        if (isset($serviceSecret)) {
            $serviceName = $request->header('s2sName');
            $expected = (string) Config::get("shekel.$serviceName-secret", '');
            if ($expected !== '' && hash_equals($expected, (string) $serviceSecret)) {
                return $next($request);
            }
            // Names the caller and the route, never the secret, so a misconfigured service is easy to find.
            Log::warning('Service secret did not match', [
                's2sName'           => $serviceName,
                'path'              => $request->path(),
                'secret_configured' => $expected !== '',
            ]);
        }

        $user = auth()->user();
        if ($user === null) {
            abort(401, 'Unauthenticated');
        }
        if (!empty($roles)) {
            PassportToken::validateUserRoleForAction($user, $roles);
        }
        return $next($request);
    }
}
