<?php

namespace Shekel\ShekelLib\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

/**
 * Service-to-service only: lets a request through only with the calling service's secret, the
 * `s2s` header matching the configured, non-empty `shekel.<s2sName>-secret` (what ShekelBaseService
 * sends). Anything else, a user's token included, is refused with 401. Unlike s2s_or_role.required,
 * it never lets a request through without that secret. Register it as s2s.required.
 */
class ShekelServiceSecretMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $serviceSecret = $request->header('s2s');
        $serviceName = $request->header('s2sName');

        // The name builds the config key, so only a plain service name is looked up.
        if (is_string($serviceSecret) && is_string($serviceName) && preg_match('/^[a-z_-]+$/', $serviceName)) {
            $expected = Config::get("shekel.$serviceName-secret");
            if (is_string($expected) && $expected !== '' && hash_equals($expected, $serviceSecret)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Service authentication failed.'], Response::HTTP_UNAUTHORIZED);
    }
}
