<?php

namespace Shekel\ShekelLib\ServiceAuth;

/**
 * Server-owned caller policy (spec §10.1): maps the signed `aud` (OAuth client id) of a service
 * token to a logical service name. Config: shekel.service_auth.callers (client id => name). A
 * client that is not listed is no caller at all.
 */
class CallerPolicy
{
    /**
     * @param array<string, string> $callers
     */
    public function __construct(private array $callers)
    {
    }

    public static function fromConfig(): self
    {
        $callers = ServiceAuthConfig::get('callers', []);

        return new self(is_array($callers) ? $callers : []);
    }

    public function serviceFor(string $clientId): ?string
    {
        $service = $this->callers[$clientId] ?? null;

        return is_string($service) && $service !== '' ? $service : null;
    }
}
