<?php

namespace Shekel\ShekelLib\Middleware\Tenant;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shekel\ShekelLib\Utils\TenantClient;

class CheckClientMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response|RedirectResponse | JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->hasHeader('x-client-id')) {
            return response()->json(['message' => 'Client Id is required'], 400);
        }
        TenantClient::flushClient();
        TenantClient::setClientId($request->header('x-client-id'));
        return $next($request);
    }
}