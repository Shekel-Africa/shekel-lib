<?php

namespace Shekel\ShekelLib\Repositories;

use Illuminate\Support\Facades\Cache;
use Shekel\ShekelLib\Services\v3\AuthService;

class ClientRepository
{
    const CACHE_STORE= 'client_redis';
    public function __construct(
        private AuthService   $authService,
    )
    {
    }

    public function getClient(string $id) {
        return Cache::store(self::CACHE_STORE)->remember($id, 14440, function () use ($id) {
            return $this->authService->getClient($id)->object()?->data;
        });
    }

    public function getClientSettings(string $id)
    {
        return Cache::store(self::CACHE_STORE)->remember($id . "-settings", 14440, function () use ($id) {
            return $this->authService->getClientSetting($id)->object()?->data;
        });
    }
    public function getClientWorkflows(string $id)
    {
        return Cache::store(self::CACHE_STORE)->remember($id . "-workflows", 14440, function () use ($id) {
            return $this->authService->getClientWorkflow($id)->object()?->data;
        });
    }

    public function getAllClientConnection() {
        return Cache::store(self::CACHE_STORE)->remember( "client-connections", 600, function () {
            return $this->authService->getAllClientConnection()->object()?->data;
        });
    }

    public function getClientByReference(string $ref) {
        return Cache::store(self::CACHE_STORE)->remember("ref-$ref", 14440, function () use ($ref) {
            return $this->authService->getClientByReference($ref)->object()?->data;
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