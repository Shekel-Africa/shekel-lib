<?php

namespace Shekel\ShekelLib\ServiceAuth\Token;

use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Shekel\ShekelLib\ServiceAuth\Jwks\JwksException;
use Shekel\ShekelLib\ServiceAuth\Jwks\JwksKeySet;
use Shekel\ShekelLib\ServiceAuth\ServiceAuthConfigurationException;
use Shekel\ShekelLib\ServiceAuth\Support\Jwt;
use Throwable;

/**
 * Verifies a user access token issued by authentication-api (Passport) against the OP JWKS before
 * any of its claims are used.
 *
 * Passport access tokens are RS256 and carry no kid, iss or tenant_id. So:
 * - the algorithm is pinned to RS256 (none, HS*, ES* and anything else are refused before any key
 *   is touched; the JWKS only yields RSA keys pinned to RS256);
 * - with a kid, only that key is used; without one, each published key is tried (bounded by
 *   MAX_CANDIDATE_KEYS), and if none verifies the JWKS is refreshed once (rate limited) to pick up
 *   a newly rotated key;
 * - exp is required; exp, nbf and iat are checked with the configured leeway.
 *
 * Returns null for any token that cannot be verified. Throws ServiceAuthConfigurationException
 * when the JWKS URL is not configured.
 */
class AccessTokenVerifier
{
    public const ALGORITHM = 'RS256';
    public const MAX_TOKEN_BYTES = 8192;
    public const MAX_CANDIDATE_KEYS = 4;

    public function __construct(
        private JwksKeySet $keys,
        private string $jwksUrl,
        private int $leeway = 60,
    ) {
        if ($keys->algorithm() !== self::ALGORITHM) {
            throw new \InvalidArgumentException('The OP key set must be RS256.');
        }
    }

    /**
     * The verified claims, or null if the token is not a valid, current RS256 token signed by a
     * key in the OP JWKS.
     *
     * @throws ServiceAuthConfigurationException
     */
    public function verify(?string $jwt): ?array
    {
        if ($this->jwksUrl === '') {
            throw ServiceAuthConfigurationException::missing('jwks_url', 'SERVICE_AUTH_JWKS_URL', 'verify access tokens');
        }
        if ($jwt === null || $jwt === '' || strlen($jwt) > self::MAX_TOKEN_BYTES) {
            return null;
        }

        try {
            $header = Jwt::header($jwt);
        } catch (Throwable $e) {
            return null;
        }
        if (($header['alg'] ?? null) !== self::ALGORITHM) {
            return null;
        }

        $claims = isset($header['kid'])
            ? $this->verifyWithKid($jwt, $header['kid'])
            : $this->verifyWithoutKid($jwt);
        if ($claims === null || !isset($claims['exp']) || !is_numeric($claims['exp'])) {
            return null;
        }

        return $claims;
    }

    private function verifyWithKid(string $jwt, $kid): ?array
    {
        if (!is_string($kid) || $kid === '') {
            return null;
        }
        try {
            $key = $this->keys->getKey($kid);
        } catch (Throwable $e) {
            return null;
        }

        return $this->decode($jwt, $key);
    }

    private function verifyWithoutKid(string $jwt): ?array
    {
        $tried = [];
        foreach ([false, true] as $refresh) {
            try {
                $keys = $this->keys->keys($refresh);
            } catch (JwksException $e) {
                return null;
            }
            foreach (array_slice($keys, 0, self::MAX_CANDIDATE_KEYS, true) as $kid => $key) {
                if (isset($tried[$kid])) {
                    continue;
                }
                $tried[$kid] = true;
                $result = $this->decodeOrSignatureFailure($jwt, $key);
                if ($result !== false) {
                    return $result; // verified (claims) or verified-but-invalid (null)
                }
            }
        }

        return null;
    }

    private function decode(string $jwt, Key $key): ?array
    {
        $result = $this->decodeOrSignatureFailure($jwt, $key);

        return $result === false ? null : $result;
    }

    /**
     * Claims if valid; null if the signature verified but the token is not valid now (expired,
     * not yet valid, malformed claims); false if the signature does not verify with $key.
     *
     * @return array|null|false
     */
    private function decodeOrSignatureFailure(string $jwt, Key $key)
    {
        try {
            return Jwt::decode($jwt, $key, $this->leeway);
        } catch (SignatureInvalidException $e) {
            return false;
        } catch (Throwable $e) {
            return null;
        }
    }
}
