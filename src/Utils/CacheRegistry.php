<?php

namespace Shekel\ShekelLib\Utils;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;

class CacheRegistry
{
    protected static function usingRedis(): bool
    {
        $cache = strtolower(Config::get("cache.default"));
        return $cache === 'redis';
    }

    protected static function versionKey(string $prefix, int|string $id): string
    {
        return "{$prefix}:{$id}:v";
    }

    protected static function tag(string $prefix, int|string $id): string
    {
        return "{$prefix}:{$id}";
    }

    public static function register(array|int|string $ids, string $cacheKey, string $prefix = 'car'): string
    {
        if (!self::usingRedis()) return $cacheKey;

        // Normalize as set for deterministic key generation
        $ids = array_values(array_unique(array_map('strval', (array)$ids)));
        sort($ids, SORT_STRING);

        $tags = array_map(
            fn($id) => self::tag($prefix, $id),
            $ids
        );

        // Use the SAME key format that forget() increments
        $versionKeys = array_map(fn($tag) => "{$tag}:v", $tags);
        $versions = Redis::mget($versionKeys);

        // Important: default to 0 so first INCR invalidates to 1
        $versions = array_map(fn($v) => (int)($v ?? 0), $versions);

        $hash = md5(implode(':', $versions));

        return "{$cacheKey}:{$hash}";
    }

    public static function forget(int|string $id, string $prefix = 'car'): void
    {
        if (!self::usingRedis()) return;

        // First call moves missing key from null -> 1, which now busts cache
        Redis::incr(self::versionKey($prefix, $id));
    }

    public static function remember(array|int|string $ids, string $key, $ttl, Closure $callback, string $prefix = 'car')
    {
        $key = self::register($ids, $key, $prefix);

        return Cache::remember($key, $ttl, $callback);
    }
}