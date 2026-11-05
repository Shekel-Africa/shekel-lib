<?php

namespace Shekel\ShekelLib\ServiceAuth\Client;

/**
 * Scrubs credentials from what gets logged or sent to Sentry/APM: Authorization-like headers,
 * bearer tokens and JWT-shaped strings.
 *
 * - Monolog processor (Monolog 2 arrays and Monolog 3 LogRecord): add `new RedactsAuthorization()`
 *   to a channel's processors (config/logging.php 'tap' or ->pushProcessor()).
 * - Sentry: 'before_send' => [RedactsAuthorization::class, 'sentryBeforeSend'].
 * - Anywhere: RedactsAuthorization::headers($headers), ::value($array), ::string($text).
 */
class RedactsAuthorization
{
    public const REDACTED = '[redacted]';

    private const SENSITIVE_KEYS = [
        'authorization', 'proxy-authorization', 'x-token', 's2s', 'cookie', 'set-cookie',
        'access_token', 'refresh_token', 'id_token', 'client_secret', 'password', 'token', 'jws',
    ];

    public function __invoke($record)
    {
        if (is_array($record)) {
            $record['message'] = self::string((string) ($record['message'] ?? ''));
            $record['context'] = self::value($record['context'] ?? []);
            $record['extra'] = self::value($record['extra'] ?? []);

            return $record;
        }
        if (is_object($record) && method_exists($record, 'with')) {
            return $record->with(
                message: self::string((string) $record->message),
                context: self::value($record->context),
                extra: self::value($record->extra),
            );
        }

        return $record;
    }

    /**
     * Redact sensitive headers (case-insensitive names; values may be strings or lists).
     */
    public static function headers(array $headers): array
    {
        return self::value($headers);
    }

    /**
     * Recursively redact sensitive keys and token-shaped strings.
     */
    public static function value($value)
    {
        if (is_string($value)) {
            return self::string($value);
        }
        if ($value instanceof \Throwable) {
            // Exception messages can carry headers or bodies; keep only a scrubbed summary.
            return self::string(get_class($value).': '.$value->getMessage().' at '.$value->getFile().':'.$value->getLine());
        }
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $key => $item) {
            $out[$key] = is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)
                ? (is_array($item) ? array_map(fn () => self::REDACTED, $item) : self::REDACTED)
                : self::value($item);
        }

        return $out;
    }

    public static function string(string $text): string
    {
        $text = preg_replace('/\b(Bearer|Basic)\s+[A-Za-z0-9\-._~+\/]+=*/i', '$1 '.self::REDACTED, $text) ?? $text;

        $text = preg_replace('/\beyJ[A-Za-z0-9_-]{2,}\.[A-Za-z0-9_-]{2,}\.[A-Za-z0-9_-]*/', self::REDACTED, $text) ?? $text;

        $fields = '(?:client_secret|access_token|refresh_token|id_token|password|token)';
        // JSON: the whole string value, escapes included.
        $text = preg_replace('/("'.$fields.'"\s*:\s*)"(?:[^"\\\\]|\\\\.)*"/i', '$1"'.self::REDACTED.'"', $text) ?? $text;

        // Form-encoded: everything up to the next "&" (or the end).
        return preg_replace('/((?:^|[?&\s])'.$fields.'=)[^&]*/i', '$1'.self::REDACTED, $text) ?? $text;
    }

    /**
     * Sentry 'before_send' callback: scrubs the event's request (headers, cookies, data), extra
     * and exception messages.
     */
    public static function sentryBeforeSend($event, $hint = null)
    {
        if (!is_object($event)) {
            return $event;
        }
        if (method_exists($event, 'getRequest') && method_exists($event, 'setRequest')) {
            $event->setRequest(self::value($event->getRequest()));
        }
        if (method_exists($event, 'getExtra') && method_exists($event, 'setExtra')) {
            $event->setExtra(self::value($event->getExtra()));
        }
        if (method_exists($event, 'getExceptions')) {
            foreach ($event->getExceptions() as $exception) {
                if (is_object($exception) && method_exists($exception, 'getValue') && method_exists($exception, 'setValue')) {
                    $exception->setValue(self::string((string) $exception->getValue()));
                }
            }
        }

        return $event;
    }
}
