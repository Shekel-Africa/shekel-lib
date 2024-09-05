<?php

namespace Shekel\ShekelLib\Middleware\Tenant;


use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shekel\ShekelLib\Utils\TenantClient;

class SetDefaultClient
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        TenantClient::flushClient();
        /** Set the default client id */
        TenantClient::setClientId(TenantClient::getDefaultClientId());

        /** Set the db connection to the default database */
        TenantClient::setClientConnection();
        return $next($request);
    }
}