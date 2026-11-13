<?php

namespace Shekel\ShekelLib\ServiceAuth\Token;

/**
 * The verified claims of a service access token.
 */
class ServiceTokenClaims
{
    /**
     * @param string[] $scopes
     */
    public function __construct(
        public string $clientId,
        public string $tenantId,
        public array $scopes,
        public string $issuer,
        public ?string $jti,
        public int $expiresAt,
        public array $raw,
    ) {
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
