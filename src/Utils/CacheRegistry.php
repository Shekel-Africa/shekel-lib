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

    protected static function versionKey(int|string $id=null, string $prefix=null): string
    {
        $pr = self::getPrefix($prefix);
        $t = "{$pr}:{$id}:v";
        return $t;
    }

    protected static function tag(int|string $id, string $prefix=null): string
    {
        $pr = self::getPrefix($prefix);
        return "{$pr}:{$id}";
    }

    protected static function getPrefix($prefix=null){
        $app = Config::get("app.name");
        if ($prefix) $app .= ":{$prefix}";
        return $app;
    }

    public static function register(array|int|string $ids, string $cacheKey, string $prefix = null): string
    {
        if (!self::usingRedis()) return $cacheKey;
        $ids = array_values(array_unique(array_map('strval', (array) $ids)));
        sort($ids, SORT_STRING);
        if (empty($ids)) {
            // Avoid a constant hash that can never be invalidated
            return "{$cacheKey}:0";
        }
        $versionKeys = array_map(fn($id) => self::versionKey($id, $prefix), $ids);
        $versions = Redis::mget($versionKeys);
        $versions = array_map(fn($v) => (int)($v ?? 0), $versions);
        return "{$cacheKey}:" . md5(implode(':', $versions));
    }

    public static function forget(int|string $id, string $prefix=null): void
    {
        if (!self::usingRedis()) return;

        // First call moves missing key from null -> 1, which now busts cache
        $id = trim((string)$id);
        $key = self::versionKey($id, $prefix);
        $new = Redis::incr($key);
    }

    public static function remember(array|int|string $ids, string $key, $ttl, Closure $callback, string $prefix =null)
    {
        $key = self::register($ids, $key, $prefix);

        return Cache::remember($key, $ttl, $callback);
    }
}