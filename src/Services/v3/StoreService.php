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
}