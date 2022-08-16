<?php

namespace Shekel\ShekelLib\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonMiddleware {
    
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('Access-Control-Allow-Origin', '*');
        $request->headers->set('Access-Control-Allow-Methods', '*');
        $request->headers->set('Access-Control-Allow-Credentials', true);
        $request->headers->set('Access-Control-Allow-Headers', 'X-Requested-With,Content-Type,X-Token-Auth,Authorization');
        return $next($request);
    }
}