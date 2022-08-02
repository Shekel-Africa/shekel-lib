<?php

namespace Shekel\ShekelLib;

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
}