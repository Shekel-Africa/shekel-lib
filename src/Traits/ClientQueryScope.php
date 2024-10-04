<?php

namespace Shekel\ShekelLib\Traits;

use Shekel\ShekelLib\Utils\TenantClient;

trait ClientQueryScope
{
    public function scopeClientId($q, $clientId)
    {
        return $q->where('client_id', $clientId);
    }

    public function applyTenantClientScope($q)
    {
        return $q->where('client_id', TenantClient::getClientId());
    }
}