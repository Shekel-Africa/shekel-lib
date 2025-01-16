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

    public function getCarsList(array $ids, array $headers=[], array $fields=[],  array $query=[]) {
        $url = "/cars/list";
        $data = [
            'ids' => $ids,
        ];
        if (isset($fields)) {
            $data['fields'] = $fields;
        }
        return $this->handleRequest($this->client->withHeaders($headers)
            ->withOptions(['query' => $query])->post($url, $data));
    }
    public function getRepairsList(array $ids, array $headers=[], array $query=[]) {
        $url = "/repairs/list";
        return $this->handleRequest($this->client->withHeaders($headers)
            ->withOptions(['query' => $query])->post($url, ['ids' => $ids]));
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

    public function viewRental(string $rentalId) {
        $url = "/rental/$rentalId";
        return $this->handleRequest($this->client->get($url));
    }
    public function markRentalAsPaid(string $rentalId, $amountPaid) {
        $url = "/rental/$rentalId/paid";
        return $this->handleRequest($this->client->post($url, [
            'amountPaid' => $amountPaid
        ]));
    }
}
