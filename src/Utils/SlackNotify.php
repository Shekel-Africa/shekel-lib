<?php

namespace Shekel\ShekelLib\Utils;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class SlackNotify
{
    const TYPE = ['success' => "#4BB543", 'error' => "#D00000"];
    public static function sendMessage(array $message, $type="error", $channel='#error-alerts')
    {
        if (in_array(Config::get("app.env"), ['testing', 'local'])) {
            return null;
        }
        $message = Arr::dot($message);
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
        try {
            return Http::post($hookUrl, $payload);
        } catch(\Throwable $th) {
            logger("Slack Notify Error", $payload);
        }
    }

    private static function arr_to_string(array $message): string
    {
        $message = Arr::dot($message);
        $text = "";
        foreach($message as $key => $val) {
            try {
                $text .= "$key: $val\n";
            } catch(\Throwable $th) {
            }
        }
        return $text;
    }
}
