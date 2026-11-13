<?php

namespace Shekel\ShekelLib\Services\v3;

class StoreService extends ShekelBaseService
{
    public function __construct()
    {
        parent::__construct('store');
    }

    public function getStore(string $id) {
        return $this->handleRequest($this->client->get("/store/$id"));
    }

    /**
     * @param string $id
     * @param array{car: array, assessments?:array, features?: array, uploads?: array, store_car?:array}  $data
     */
    public function addCarToStore(string $id, array $data) {
        return $this->handleRequest($this->client->post("/store/$id/cars/add", $data));
    }

    public function publish(string $id) {
        return $this->handleRequest($this->client->post("/store/$id/publish"));
    }

    /**
     * @param array{user_id: string, company_id?: string|null, brands: array<string>} $data
     */
    public function syncHotDealPreferences(array $data)
    {
        return $this->handleRequest(
            $this->client->post('/admin/auction/hot-deals/preferences/sync', $data)
        );
    }
}