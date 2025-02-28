<?php

namespace Shekel\ShekelLib\Utils;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class AmplitudeTracker
{
    protected PendingRequest|Http $client;

    /**
     * @param array{user_id: string, event_type: string} $event
     * @throws ConnectionException
     */
    public static function sendEvent(array $event): PromiseInterface|Response
    {
        $headers = [
            'Accept' => '*/*',
            'Content-Type' => 'application/json',
        ];
        $event['client_id'] = TenantClient::getClientId();
        $payload = [
            "api_key" => Config::get("amplitude.apiKey"),
            "events" => [$event]
        ];
        return Http::withHeaders($headers)->post('https://api2.amplitude.com/2/httpapi', $payload);
    }
}