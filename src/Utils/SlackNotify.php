<?php

namespace Shekel\ShekelLib\Utils;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class SlackNotify
{
    public static function sendMessage(string $message, $channel='#error-alerts') {
        $hookUrl = Config::get("shekel.slack_webhook");
        $payload = [
            "channel" => $channel,
            "text" => $message
        ];
        return Http::asForm()->post($hookUrl, ['payload' => $payload]);
    }
}
