<?php

namespace Shekel\ShekelLib\Services\v3;

class LocationService extends ShekelBaseService
{
    public function __construct()
    {
        parent::__construct('auth');
    }

    public function getItem(string $id, $extra = null)
    {
        $url = "/location/$id";
        return $this->handleRequest($this->client->get($url, $extra));
    }

    public function createItem($data) {
        $url = "/location";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function listItems(array $ids=[]) {
        $url = "/location/list";
        return $this->handleRequest($this->client->post($url, ['ids' => $ids]));
    }
    public function searchAndlistIds($country, $state, $city=null) {
        $url = "/location/search/ids";
        $data = [
            'country' => $country,
            'state' => $state,
        ];
        if (isset($city)) {
           $data['city'] = $city;
        }
        return $this->handleRequest($this->client->get($url, $data));
    }
}
