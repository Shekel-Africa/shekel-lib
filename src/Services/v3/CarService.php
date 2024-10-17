<?php

namespace Shekel\ShekelLib\Services\v3;


class CarService extends ShekelBaseService {

    public function __construct()
    {
        parent::__construct('car');
    }

    public function getItem(string $id, $extra=[]) {
        $url = "/cars/$id";
        return $this->handleRequest($this->client->get($url));
    }
    public function getRepair(string $id, $extra=[]) {
        $url = "/repairs/$id";
        return $this->handleRequest($this->client->get($url));
    }

    public function getCar(string $id, $extra=[]) {
        return $this->getItem($id, $extra);
    }
    public function editItem(string $id, array $data)
    {
        $url = "/cars/$id";
        return $this->handleRequest($this->client->post($url, $data));
    }
    public function editRepair(string $id, array $data)
    {
        $url = "/repairs/$id";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function partnerGetCar(string $car_id) {
        $url ="/partner/$car_id";
        return $this->handleRequest($this->client->get($url));
    }

    public function getCarsList(array $ids, array $headers=[]) {
        $url = "/cars/list";
        return $this->handleRequest($this->client->withHeaders($headers)->post($url, ['ids' => $ids]));
    }
    public function getRepairsList(array $ids, array $headers=[]) {
        $url = "/repairs/list";
        return $this->handleRequest($this->client->withHeaders($headers)->post($url, ['ids' => $ids]));
    }

    public function generateCarInsurance($car_id) {
        $url = "/insurance/$car_id";
        return $this->handleRequest($this->client->get($url));
    }

    public function removeCarInsurance($car_id) {
        $url= "/insurance/$car_id";
        return $this->handleRequest($this->client->delete($url));
    }

    public function editOffer($offer_id, $data) {
        $url = "/trade/offer/$offer_id";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function markAsSold($offer_id) {
        $url = "/trade/offer/$offer_id/sold";
        return $this->handleRequest($this->client->post($url, [
            'sold' => true
        ]));
    }

    public function markAsCancelled($offer_id) {
        $url = "/trade/offer/$offer_id/cancelled";
        return $this->handleRequest($this->client->post($url));
    }

    public function getOfferDetail($offer_id, $showUploads=true) {
        $url = "/trade/offer/$offer_id";
        return $this->handleRequest($this->client->get($url, ['showUploads' => $showUploads]));
    }

    public function changeCarOwnership(array $carIds) {
        $url = 'cars/ownership/change';
        return $this->handleRequest($this->client->post($url, [
            'cars' => $carIds
        ]));
    }
}
