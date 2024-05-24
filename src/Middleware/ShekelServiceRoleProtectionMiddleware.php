<?php

namespace Shekel\ShekelLib\Middleware;

use Illuminate\Support\Facades\Config;
use Shekel\ShekelLib\Utils\PassportToken;

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
            if($serviceSecret ===  Config::get("shekel.$serviceName-secret")) {
                return next($request);
            }
        }
        PassportToken::validateUserRoleForAction(auth()->user(), $roles);
        return $next($request);
    }
}