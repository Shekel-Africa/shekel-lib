<?php

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class CarService extends ShekelBaseService {

    public function __construct()
    {
        parent::__construct('car');
    }

    /**
     * Undocumented function
     *
     * @return Response
     */
    public function getCar(string $car_id) {
        return $this->getItem($car_id);
    }

    public function partnerGetCar(string $car_id) {
        return $this->getCar("partner/$car_id");
    }

    public function getCarsList(array $ids) {
        $url = "/list";
        return $this->handleRequest($this->client->post($url, ['ids' => $ids]));
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
        $url = '/ownership/change';
        return $this->handleRequest($this->client->post($url, [
            'cars' => $carIds
        ]));
    }
}
