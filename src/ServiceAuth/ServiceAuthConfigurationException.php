<?php

namespace Shekel\ShekelLib\ServiceAuth;

use RuntimeException;

/**
 * Required service-auth configuration is missing. Thrown on first verification use.
 */
class ServiceAuthConfigurationException extends RuntimeException
{
    public static function missing(string $configKey, string $envName, string $purpose): self
    {
        return new self("config('shekel.service_auth.$configKey') is not set ($envName). It is required to $purpose.");
    }
}
