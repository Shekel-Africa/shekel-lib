<?php

namespace Shekel\ShekelLib\ServiceAuth\Token;

use Shekel\ShekelLib\ServiceAuth\Jwks\JwksKeySet;
use Shekel\ShekelLib\ServiceAuth\Support\Jwt;
use Throwable;

/**
 * Authenticates an OAuth service access token issued by authentication-api (spec §10.1):
 * RS256 only (alg=none, HS256 and every other alg refused before any key is touched), kid
 * required and looked up in the OP JWKS only, iss = the user-realm issuer, exp/nbf/iat with the
 * configured leeway, client_kind = service, a non-empty tenant_id and a client id in aud.
 *
 * Failures are 401. Authorization (scopes, caller, tenant) is the middleware's job and is 403.
 */
class ServiceTokenVerifier
{
    public const ALGORITHM = 'RS256';

    public function __construct(
        private JwksKeySet $keys,
        private string $issuer,
        private int $leeway = 60,
    ) {
        if ($keys->algorithm() !== self::ALGORITHM) {
            throw new \InvalidArgumentException('The OP key set must be RS256.');
        }
    }

    /**
     * @throws ServiceAuthException (401)
     */
    public function verify(string $jwt): ServiceTokenClaims
    {
        if ($this->issuer === '') {
            throw ServiceAuthException::unauthenticated('issuer_not_configured');
        }
        if ($jwt === '' || strlen($jwt) > 8192) {
            throw ServiceAuthException::unauthenticated('missing_token');
        }

        try {
            $header = Jwt::header($jwt);
        } catch (Throwable $e) {
            throw ServiceAuthException::unauthenticated('malformed_token');
        }
        if (($header['alg'] ?? null) !== self::ALGORITHM) {
            throw ServiceAuthException::unauthenticated('alg_not_allowed');
        }
        if (isset($header['typ']) && (!is_string($header['typ']) || !in_array(strtolower($header['typ']), ['jwt', 'at+jwt'], true))) {
            throw ServiceAuthException::unauthenticated('typ_not_allowed');
        }
        if (!isset($header['kid']) || !is_string($header['kid']) || $header['kid'] === '') {
            throw ServiceAuthException::unauthenticated('missing_kid');
        }

        try {
            $key = $this->keys->getKey($header['kid']);
        } catch (Throwable $e) {
            throw ServiceAuthException::unauthenticated('unknown_kid');
        }

        try {
            $claims = Jwt::decode($jwt, $key, $this->leeway);
        } catch (Throwable $e) {
            throw ServiceAuthException::unauthenticated('invalid_token');
        }

        if (!isset($claims['exp']) || !is_numeric($claims['exp'])) {
            throw ServiceAuthException::unauthenticated('missing_exp');
        }
        if (!isset($claims['iss']) || !is_string($claims['iss']) || !hash_equals($this->issuer, $claims['iss'])) {
            throw ServiceAuthException::unauthenticated(isset($claims['iss']) ? 'wrong_issuer' : 'missing_iss');
        }
        if (($claims['client_kind'] ?? null) !== 'service') {
            throw ServiceAuthException::unauthenticated('not_a_service_token');
        }
        $tenantId = $claims['tenant_id'] ?? null;
        if (!is_string($tenantId) || trim($tenantId) === '') {
            throw ServiceAuthException::unauthenticated('missing_tenant_id');
        }
        $clientId = self::audience($claims['aud'] ?? null);
        if ($clientId === null) {
            throw ServiceAuthException::unauthenticated('missing_aud');
        }

        $scopes = $claims['scopes'] ?? [];
        if (!is_array($scopes) || array_filter($scopes, fn ($scope) => !is_string($scope)) !== []) {
            throw ServiceAuthException::unauthenticated('malformed_scopes');
        }

        return new ServiceTokenClaims(
            $clientId,
            $tenantId,
            array_values($scopes),
            $claims['iss'],
            isset($claims['jti']) && is_string($claims['jti']) ? $claims['jti'] : null,
            (int) $claims['exp'],
            $claims,
        );
    }

    /**
     * The OAuth client id from aud: a non-empty string, or a one-element array of one.
     */
    private static function audience($aud): ?string
    {
        if (is_array($aud) && count($aud) === 1) {
            $aud = reset($aud);
        }

        return is_string($aud) && $aud !== '' ? $aud : null;
    }
}
