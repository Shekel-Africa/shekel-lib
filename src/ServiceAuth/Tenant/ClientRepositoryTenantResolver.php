<?php

namespace Shekel\ShekelLib\ServiceAuth\Tenant;

use Shekel\ShekelLib\Exceptions\ShekelInvalidArgumentException;
use Shekel\ShekelLib\Repositories\ClientRepository;
use Shekel\ShekelLib\Utils\TenantClient;

/**
 * Default resolver on shekel-lib's existing tenant mechanism: the client is looked up through
 * ClientRepository (cached), and selected the way ClientTenantSwitch does it (TenantClient client
 * id, client object and DB connection).
 */
class ClientRepositoryTenantResolver implements TenantResolver
{
    /** @var array<string, object|null> per-request memo */
    private array $resolved = [];

    public function __construct(private ClientRepository $repository)
    {
    }

    public function isKnown(string $tenantId): bool
    {
        return $this->client($tenantId) !== null;
    }

    public function select(string $tenantId): void
    {
        $client = $this->client($tenantId);
        TenantClient::flushClient();
        TenantClient::setClientId($tenantId);
        TenantClient::switchTenantConnection($client->connection ?? null);
        if ($client !== null) {
            TenantClient::setClientObject($client);
        }
    }

    private function client(string $tenantId): ?object
    {
        if (!array_key_exists($tenantId, $this->resolved)) {
            try {
                $client = $this->repository->getClient($tenantId);
            } catch (ShekelInvalidArgumentException $e) {
                if (in_array($e->getCode(), [400, 404, 422], true)) {
                    $client = null;
                } else {
                    throw $e;
                }
            }
            $this->resolved[$tenantId] = is_object($client) ? $client : null;
        }

        return $this->resolved[$tenantId];
    }
}
