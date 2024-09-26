<?php

namespace Shekel\ShekelLib\Middleware\Tenant;


use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shekel\ShekelLib\Repositories\ClientRepository;
use Shekel\ShekelLib\Utils\ShekelAuth;
use Shekel\ShekelLib\Utils\TenantClient;

class SetDefaultClient
{

    public function __construct(private ClientRepository $clientRepository) {

    }
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        TenantClient::flushClient();
        /** Set the default client id */
        TenantClient::setClientId(TenantClient::getDefaultClientId());

        if (TenantClient::getClientId()) {
            $client = $this->clientRepository->getClient(TenantClient::getClientId());
            TenantClient::setClientObject($client);
        }

        ShekelAuth::setAuthToken($request->bearerToken());
        ShekelAuth::setAuthXToken($request->header('x-token'));
        /** Set the db connection to the default database */
        TenantClient::setClientConnection();
        return $next($request);
    }
}