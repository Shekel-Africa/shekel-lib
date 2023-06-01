<?php

namespace Shekel\ShekelLib\Services;

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
}
