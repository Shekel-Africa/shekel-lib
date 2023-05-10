<?php

namespace Shekel\ShekelLib\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shekel\ShekelLib\Utils\PassportToken;

class RoleRouteProtectionMiddleware
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
        $request->headers->set('Accept', 'application/json');
        PassportToken::validateUserRoleForAction(auth()->user(), $roles);
        return $next($request);
    }
}
