<?php

namespace Shekel\ShekelLib\Utils;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class SlackNotify
{
    const TYPE = ['success' => "#4BB543", 'error' => "#D00000"];
    public static function sendMessage(array $message, $type="error", $channel='#error-alerts'): \Illuminate\Http\Client\Response
    {
        $hookUrl = Config::get("shekel.slack_webhook");
        $payload = [
            "channel" => $channel,
            "username" => 'bot',
            "icon_emoji" => ":ghost:",
            "attachments" => [
                [
                    "color"=> self::TYPE[strtolower($type)],
                    "fields" => [
                        [
                            "title" => "Notes",
                            "value" => self::arr_to_string($message),
                            "short" => false
                        ]
                    ]
                ]
            ]
        ];
        return Http::post($hookUrl, $payload);
    }

    private static function arr_to_string(array $message): string
    {
        $text = "";
        foreach($message as $key => $val) {
            $text .= "$key: $val\n";
        }
        return $text;
    }
}
