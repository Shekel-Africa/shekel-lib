<?php

namespace Shekel\ShekelLib\Utils;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class TenantClient
{
    const clientKey = 'tenantClientId';

    public static function getDefaultClientId(): string|null
    {
        return Config::get('tenant.default_client_id');
    }

    /**
     * @return string|null
     */
    public static function getClientId(): string|null
    {
        return Session::get(self::clientKey);
    }

    /**
     * @param string $clientId
     */
    public static function setClientId(string $clientId): void
    {
        Session::put(self::clientKey, $clientId);
    }

    public static function flushClient(): void
    {
        Session::forget(self::clientKey);
    }

    public static function setClientConnection($connection = null): void
    {
        if (!isset($connection)) {
            $connection = getenv('DB_CONNECTION');
        }
        Config::set('database.default', $connection);
        Config::set('database.connections.tenant', Config::get("database.connections.$connection"));
    }

    public static function flushClientConnection(): void
    {
        $connection = getenv('DB_CONNECTION');
        Config::set('database.default', $connection);
        Config::set('database.connections.tenant', []);
        DB::purge('tenant');
    }

    public static function switchTenantConnection($connection=null): void
    {
        TenantClient::flushClientConnection();
        TenantClient::setClientConnection($connection);
    }
}