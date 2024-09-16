<?php

namespace Shekel\ShekelLib\Services\v3;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Shekel\ShekelLib\Utils\ShekelAuth;
use Shekel\ShekelLib\Utils\SlackNotify;
use Shekel\ShekelLib\Utils\TenantClient;

class ShekelBaseService {

    protected PendingRequest|Http $client;

    private string|null $clientSecret;
    private string|null $serviceName;
    protected string|null $token;
    protected string|null $baseUrl;

    public function __construct($serviceName)
    {
        $this->serviceName = $serviceName;
        $this->baseUrl = Config::get("shekel.$serviceName").'/v3';
        $this->clientSecret = Config::get("shekel.$serviceName-secret");
        $this->token = ShekelAuth::getAuthToken();
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

    public function setToken($token): void
    {
        $this->token = $token;
        $this->setRequestOption();
    }

    public function setRequestOption(): void
    {
        $this->client = Http::WithToken($this->token)->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            's2s' => $this->clientSecret,
            's2sName' => $this->serviceName,
            'x-client-id' => TenantClient::getClientId(),
            'x-token' => ShekelAuth::getXToken()
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