<?php

namespace Shekel\ShekelLib\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Shekel\ShekelLib\Utils\SlackNotify;

class ShekelBaseService {
    /**
     * @var Http $client
     */
    protected $client;

    private string|null $clientSecret;
    private string|null $serviceName;
    protected $token;
    protected $baseUrl;

    public function __construct($serviceName)
    {
        $this->serviceName = $serviceName;
        $this->baseUrl = Config::get("shekel.$serviceName");
        $this->clientSecret = Config::get("shekel.$serviceName-secret");
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
            's2s' => $this->clientSecret,
            's2sName' => $this->serviceName
        ])->withOptions([
            'allow_redirects' => ['strict' => true],
        ])->baseUrl($this->baseUrl);
    }

    protected function handleRequest($request) {
        if (!$request->successful() && !$request->unauthorized()) {
            $message = [
                'environment' => getenv('APP_ENV'),
                'service' => get_class($this),
                'url' => $request->effectiveUri(),
                'message' => $request->json('message')
            ];
            try {
                SlackNotify::sendMessage($message);
            } catch (\Throwable $th) {
            }
            abort($request->status(), $request->json('message'));
        }
        return $request;
    }

}
