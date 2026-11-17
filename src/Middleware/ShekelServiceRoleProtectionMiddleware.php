<?php

namespace Shekel\ShekelLib\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Shekel\ShekelLib\Utils\PassportToken;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Lets a request in with the calling service's secret (the s2s and s2sName headers). Without it,
 * a route with $roles needs a user an earlier middleware signed in, holding one of them: a missing
 * user is refused with 401 rather than handed to the role check, where it failed with a TypeError
 * (500). A route without roles accepts the service secret only: anything else is refused with 401,
 * even with a signed-in user (S20-4646; it used to let every request through).
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

        // Without roles the route is service-only: no valid secret, no entry (S20-4646). An HTTP
        // exception, so every service renders it as 401 without a handler of its own.
        if (empty($roles)) {
            throw new UnauthorizedHttpException('s2s', 'Unauthenticated.');
        }

        $user = auth()->user();
        if ($user === null) {
            throw new UnauthorizedHttpException('s2s', 'Unauthenticated.');
        }
        PassportToken::validateUserRoleForAction($user, $roles);
        return $next($request);
    }
}
