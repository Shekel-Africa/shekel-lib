<?php

namespace Shekel\ShekelLib\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class ShekelBaseService {
    protected $client;

    public function __construct($token, $serviceName)
    {
        $this->client = Http::WithToken($token)->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->baseUrl(Config::get("shekel.$serviceName"));
    }

    public function getItem(string $id){
        $url = "/$id";
        return $this->client->get($url);
    }

    public function editItem(string $id, array $data) {
        $url ="/$id";
        return $this->client->post($url, $data);
    }
}