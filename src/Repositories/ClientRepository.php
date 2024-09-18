<?php

namespace Shekel\ShekelLib\Repositories;

use Illuminate\Support\Facades\Cache;
use Shekel\ShekelLib\Models\Client;
use Shekel\ShekelLib\Models\ClientSetting;
use Shekel\ShekelLib\Models\ClientWorkflow;

class ClientRepository extends BaseRepository
{
    public function __construct(
        Client                $model,
        private ClientSetting $clientSetting,
        private ClientWorkflow $clientWorkflow
    )
    {
        parent::__construct($model);
    }

    public function getClient(string $id)
    {
        return Cache::driver('client_redis')->remember($id, 14440, function () use ($id) {
            return $this->get($id);
        });
    }

    public function getClientSettings(string $id)
    {
        return Cache::driver('client_redis')->remember($id . "settings", 14440, function () use ($id) {
            return $this->clientSetting->allClients()->clientId($id)->all();
        });
    }
    public function getClientWorkflows(string $id)
    {
        return Cache::driver('client_redis')->remember($id . "workflows", 14440, function () use ($id) {
            return $this->clientWorkflow->allClients()->clientId($id)->all();
        });
    }
}