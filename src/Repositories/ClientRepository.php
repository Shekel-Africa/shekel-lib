<?php

namespace Shekel\ShekelLib\Repositories;

use Illuminate\Support\Facades\Cache;
use Shekel\ShekelLib\Services\v3\AuthService;

class ClientRepository
{
    public function __construct(
        private AuthService   $authService,
    )
    {
    }

    public function getClient(string $id) {
        return Cache::store('client_redis')->remember($id, 14440, function () use ($id) {
            return $this->authService->getClient($id)->object()?->data;
        });
    }

    public function getClientSettings(string $id)
    {
        return Cache::store('client_redis')->remember($id . "-settings", 14440, function () use ($id) {
            return $this->authService->getClientSetting($id)->object()?->data;
        });
    }
    public function getClientWorkflows(string $id)
    {
        return Cache::store('client_redis')->remember($id . "-workflows", 14440, function () use ($id) {
            return $this->authService->getClientWorkflow($id)->object()?->data;
        });
    }

    public function getClientSettingsByGroup(string $id, string $group): \Illuminate\Support\Collection
    {
        $settings = collect($this->getClientSettings($id));
        return $settings->where('group', $group);
    }

    public function getClientWorkflowsByGroup(string $id, string $group): \Illuminate\Support\Collection
    {
        $settings = collect($this->getClientWorkflows($id));
        return $settings->where('workflow', $group);
    }
}