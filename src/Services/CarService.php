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

    public function getCarsList(array $ids) {
        $url = "/list";
        return $this->handleRequest($this->client->post($url, ['ids' => $ids]));
    }
}
