<?php

namespace Shekel\ShekelLib\ServiceAuth\Tenant;

/**
 * Validates and selects the tenant named by a service token's tenant_id claim. Bind your own
 * implementation to replace the default ClientRepositoryTenantResolver.
 */
interface TenantResolver
{
    /**
     * Whether $tenantId is a known tenant. Throw when that cannot be determined (the middleware
     * answers 503 rather than guessing).
     */
    public function isKnown(string $tenantId): bool;

    /**
     * Make $tenantId the current tenant for the rest of the request (DB connection etc.).
     */
    public function select(string $tenantId): void;
}
