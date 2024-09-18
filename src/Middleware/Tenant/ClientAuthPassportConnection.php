<?php

namespace Shekel\ShekelLib\Middleware\Tenant;



use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Shekel\ShekelLib\Repositories\ClientRepository;
use Shekel\ShekelLib\Utils\ShekelAuth;
use Shekel\ShekelLib\Utils\TenantClient;

class ClientAuthPassportConnection
{
    public function __construct(private ClientRepository $clientRepository)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure(Request): (Response|RedirectResponse) $next
     * @param string|null $connection
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $connection = null)
    {
        if (!isset($connection)) {
            $clientId = $request->header('x-client-id');
            /** get client connection */
            if ($clientId) {
                $client = $this->clientRepository->getClient($clientId);
                $connection = $client->connection;
            }
        }
        TenantClient::setClientConnection($connection);
        ShekelAuth::setAuthToken($request->bearerToken());
        ShekelAuth::setAuthXToken($request->header('x-token'));
        return $next($request);
    }
}