<?php

namespace Shekel\ShekelLib\Middleware\Tenant;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shekel\ShekelLib\Repositories\ClientRepository;
use Shekel\ShekelLib\Utils\TenantClient;

class ClientTenantSwitch
{

    public function __construct(
        private ClientRepository $shekelClient
    )
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response|RedirectResponse|JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $client = $this->shekelClient->getClient(TenantClient::getClientId());
        TenantClient::switchTenantConnection(
            $client?->connection
        );
        TenantClient::setClientObject($client);
        return $next($request);
    }
}