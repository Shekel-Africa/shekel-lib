<?php

namespace Shekel\ShekelLib\Commands\Traits;

use Illuminate\Support\Facades\Config;
use Shekel\ShekelLib\Utils\TenantClient;

/**
 * @method option(string $string)
 */
trait TenantConnectionTrait
{

    public function setTenantConnection()
    {
        $connection = $this->option('database');
        TenantClient::setClientConnection($connection);
    }
}
