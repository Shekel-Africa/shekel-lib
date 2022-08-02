<?php 

namespace Shekel\ShekelLib\Services;

use Shekel\ShekelLib\Services\ShekelBaseService;

class CarService extends ShekelBaseService {
    
    public function __construct($token)
    {
        parent::__construct($token, 'car');
    }

    /**
     * Undocumented function
     *
     * @return Response
     */
    public function getCar(string $car_id) {
        $url = "/$car_id";
        return $this->client->get($url);
    }

    public function getCarsList(array $ids) {
        $url = "/list";
        return $this->client->post($url, ['ids' => $ids]);
    }
}