<?php

namespace Shekel\ShekelLib\Utils;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8NWorkflowNotify
{
    protected PendingRequest|Http $client;

    /**
     * @param string $path
     * @param array $data
     * @return PromiseInterface|Response|null
     * @throws ConnectionException
     */
    public static function sendEvent(string $path, array $data): PromiseInterface|Response|null
    {
        $env = strtolower(Config::get("app.env"));
        if (in_array($env, ['testing', 'local'])) {
            return null;
        }
        $url = match ($env){
            'production' => Config::get('n8nworkflow.baseUrl') . "/webhook/$path",
            default => Config::get('n8nworkflow.baseUrl') . "/webhook-test/$path"
        };
        $headers = [
            'Accept' => '*/*',
            'Content-Type' => 'application/json',
        ];
        $data['client_id'] = TenantClient::getClientId();
        try {
            return Http::withHeaders($headers)->post($url, $data);
        } catch (\Throwable $throwable) {
            Log::info('n8n could not trigger workflow', ['message' => $throwable->getMessage()]);
        }
    }
}