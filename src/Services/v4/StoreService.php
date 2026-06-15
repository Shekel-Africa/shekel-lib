<?php

namespace Shekel\ShekelLib\Services\v4;

use Shekel\ShekelLib\Services\v3\ShekelBaseService;

class StoreService extends ShekelBaseService
{
    public function __construct()
    {
        parent::__construct('store', 'v4');
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
     * @param array{car: array, assessments?:array, features?: array,
     *     uploads?: array, price:numeric, currency:string, allowed_clients:array} $data
     */
    public function publishToMarketPlace(array $data) {
        return $this->handleRequest($this->client->post("/marketplace/publish", $data));
    }

    public function getAuctionOrderById(string $id) {
        return $this->handleRequest($this->client->get("/auction/orders/$id"));
    }

    public function markOrderAsPaid(string $id) {
        return $this->handleRequest($this->client->post("/auction/orders/$id/pay"));
    }
}