<?php

namespace Shekel\ShekelLib\ServiceAuth\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use LogicException;

final class CacheLocks
{
    public static function lock(Repository $cache, string $name, int $seconds): Lock
    {
        $store = method_exists($cache, 'getStore') ? $cache->getStore() : null;
        if (!$store instanceof LockProvider) {
            throw new LogicException('shekel.service_auth.cache_store must support atomic locks.');
        }

        return $store->lock($name, $seconds);
    }
}
