<?php

namespace Shekel\ShekelLib\Commands\Tenant;

use Illuminate\Console\Command;
use Shekel\ShekelLib\Repositories\ClientRepository;
use Shekel\ShekelLib\Utils\TenantClient;

abstract class TenantAwareCommands extends Command
{
    public function __construct(private ClientRepository $clientRepository)
    {
        parent::__construct();
    }

    public function handle()
    {
        $clients = $this->clientRepository->getAllClientConnection();
        foreach ($clients as $client) {
            TenantClient::setClientId($client->id);
            TenantClient::setClientConnection($client->connection);
            $this->handleCommand();
        }
    }

    /**
     * Run Your tenant aware command
     * @return mixed
     */
    abstract public function handleCommand():mixed;
}