<?php

namespace Shekel\ShekelLib\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class ShekelBaseService {
    /**
     * @var Http $client
     */
    protected $client;
    protected $token;
    protected $baseUrl;

    public function __construct($serviceName)
    {
        $this->baseUrl = Config::get("shekel.$serviceName");
        $this->setRequestOption();
    }

    public function getItem(string $id, $extra=null){
        $url = "/$id";
        return $this->handleRequest($this->client->get($url, $extra));
    }

    public function editItem(string $id, array $data) {
        $url ="/$id";
        return $this->handleRequest($this->client->post($url, $data));
    }

    public function setToken($token) {
        $this->token = $token;
        $this->setRequestOption();
    }

    public function setRequestOption() {
        $this->client = Http::WithToken($this->token)->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->baseUrl($this->baseUrl);
    }

    protected function handleRequest($request) {
        if (!$request->successful()) {
            abort($request->status(), $request->json('message'));
        }
        return $request;
    }

}
