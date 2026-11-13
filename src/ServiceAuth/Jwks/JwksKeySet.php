<?php

namespace Shekel\ShekelLib\ServiceAuth\Jwks;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Shekel\ShekelLib\ServiceAuth\Support\CacheLocks;
use Shekel\ShekelLib\ServiceAuth\Support\Clock;
use Throwable;

/**
 * A hardened, cached JWKS (spec §10.1), used for the OP JWKS (RS256) and signature-api's snapshot
 * JWKS (ES256):
 *
 * - Only the configured URL is fetched; nothing in a token (jku, x5u, ...) is ever followed.
 * - Keys are cached in the Laravel cache. A refresh is single-flight (cache lock) and happens at
 *   most once per cooldown (60s) however many unknown kids arrive.
 * - An unknown kid is negatively cached for 5 minutes, in one bounded entry.
 * - 2s HTTP timeout, no redirects, a body cap and at most $maxKeys keys.
 * - Only keys of this set's algorithm are kept (RSA for RS256, EC P-256 for ES256), so a key can
 *   never be used with another algorithm.
 */
class JwksKeySet
{
    private const MAX_BODY_BYTES = 65536;
    private const HARD_TTL = 86400;
    private const MAX_NEGATIVE = 256;

    public function __construct(
        private string $url,
        private string $algorithm,
        private CacheRepository $cache,
        private HttpFactory $http,
        private string $name,
        private int $timeout = 2,
        private int $maxKeys = 16,
        private int $cacheTtl = 3600,
        private int $refreshCooldown = 60,
        private int $negativeTtl = 300,
    ) {
        if (!in_array($algorithm, ['RS256', 'ES256'], true)) {
            throw new \InvalidArgumentException("Unsupported JWKS algorithm $algorithm.");
        }
    }

    public function algorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * The verification key for $kid, pinned to this set's algorithm.
     *
     * @throws JwksException when the kid is unknown (after at most one rate-limited refresh) or
     *                       the JWKS cannot be loaded.
     */
    public function getKey(string $kid): Key
    {
        if (!self::isValidKid($kid)) {
            throw new JwksException('Invalid kid.');
        }

        $set = $this->cached();
        if ($set !== null && isset($set['keys'][$kid])) {
            if ($set['fetched_at'] + $this->cacheTtl <= Clock::now()) {
                // Periodic refresh so a removed key stops verifying; stale keys are kept if it fails.
                $set = $this->refresh(null) ?? $set;
            }
            if (isset($set['keys'][$kid])) {
                return $this->parse($set['keys'][$kid]);
            }
        }

        if ($this->isNegative($kid)) {
            throw new JwksException('Unknown kid.');
        }

        $set = $this->refresh($kid);
        if ($set !== null && isset($set['keys'][$kid])) {
            return $this->parse($set['keys'][$kid]);
        }

        $this->markNegative($kid);
        throw new JwksException('Unknown kid.');
    }

    public static function isValidKid(string $kid): bool
    {
        return $kid !== '' && strlen($kid) <= 128 && preg_match('/^[A-Za-z0-9._:\-]+$/', $kid) === 1;
    }

    /**
     * Single-flight refresh. Returns the newest key set available (possibly the cached one).
     */
    private function refresh(?string $kid): ?array
    {
        $lock = CacheLocks::lock($this->cache, $this->key('lock'), $this->timeout + 8);
        try {
            $lock->block($this->timeout + 1);
        } catch (LockTimeoutException $e) {
            return $this->cached();
        }

        try {
            $set = $this->cached();
            if ($kid !== null && $set !== null && isset($set['keys'][$kid])) {
                return $set; // another worker refreshed while we waited
            }
            if ($kid === null && $set !== null && $set['fetched_at'] + $this->cacheTtl > Clock::now()) {
                return $set;
            }
            // At most one fetch per cooldown, whatever the outcome.
            if (!$this->cache->add($this->key('cooldown'), 1, $this->refreshCooldown)) {
                return $set;
            }

            try {
                $keys = $this->fetch();
            } catch (Throwable $e) {
                Log::warning('Service auth: JWKS refresh failed.', ['jwks' => $this->name, 'error' => $e->getMessage()]);

                return $set;
            }

            $fresh = ['fetched_at' => Clock::now(), 'keys' => $keys];
            $this->cache->put($this->key('keys'), $fresh, self::HARD_TTL);
            $this->forgetNegative(array_keys($keys));

            return $fresh;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, array> public JWKs by kid
     */
    private function fetch(): array
    {
        $response = $this->http
            ->withOptions(['allow_redirects' => false])
            ->timeout($this->timeout)
            ->connectTimeout($this->timeout)
            ->acceptJson()
            ->get($this->url);

        if (!$response->successful()) {
            throw new JwksException('JWKS endpoint answered '.$response->status().'.');
        }
        $body = $response->body();
        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new JwksException('JWKS response too large.');
        }
        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['keys']) || !is_array($json['keys'])) {
            throw new JwksException('JWKS response has no keys.');
        }

        $keys = [];
        foreach ($json['keys'] as $jwk) {
            if (count($keys) >= $this->maxKeys) {
                Log::warning('Service auth: JWKS has more keys than the cap; extra keys ignored.', ['jwks' => $this->name, 'max' => $this->maxKeys]);
                break;
            }
            $public = $this->acceptable($jwk);
            if ($public === null || isset($keys[$public['kid']])) {
                continue;
            }
            try {
                $this->parse($public);
            } catch (Throwable $e) {
                continue;
            }
            $keys[$public['kid']] = $public;
        }
        if ($keys === []) {
            throw new JwksException('JWKS has no usable key.');
        }

        return $keys;
    }

    /**
     * The public members of $jwk if it is a signing key for this set's algorithm, else null.
     */
    private function acceptable($jwk): ?array
    {
        if (!is_array($jwk) || !isset($jwk['kid'], $jwk['kty']) || !is_string($jwk['kid']) || !self::isValidKid($jwk['kid'])) {
            return null;
        }
        if (isset($jwk['use']) && $jwk['use'] !== 'sig') {
            return null;
        }
        if (isset($jwk['alg']) && $jwk['alg'] !== $this->algorithm) {
            return null;
        }
        if ($this->algorithm === 'RS256') {
            if ($jwk['kty'] !== 'RSA' || !isset($jwk['n'], $jwk['e']) || !is_string($jwk['n']) || !is_string($jwk['e'])) {
                return null;
            }

            return ['kty' => 'RSA', 'kid' => $jwk['kid'], 'alg' => 'RS256', 'n' => $jwk['n'], 'e' => $jwk['e']];
        }
        if ($jwk['kty'] !== 'EC' || ($jwk['crv'] ?? null) !== 'P-256' || !isset($jwk['x'], $jwk['y']) || !is_string($jwk['x']) || !is_string($jwk['y'])) {
            return null;
        }

        return ['kty' => 'EC', 'kid' => $jwk['kid'], 'alg' => 'ES256', 'crv' => 'P-256', 'x' => $jwk['x'], 'y' => $jwk['y']];
    }

    private function parse(array $jwk): Key
    {
        $key = JWK::parseKey($jwk, $this->algorithm);
        if ($key === null || $key->getAlgorithm() !== $this->algorithm) {
            throw new JwksException('Unusable JWK.');
        }

        return $key;
    }

    private function cached(): ?array
    {
        $set = $this->cache->get($this->key('keys'));

        return is_array($set) && isset($set['fetched_at'], $set['keys']) && is_array($set['keys']) ? $set : null;
    }

    private function isNegative(string $kid): bool
    {
        $negative = $this->cache->get($this->key('negative'));

        return is_array($negative) && isset($negative[$kid]) && $negative[$kid] > Clock::now();
    }

    private function markNegative(string $kid): void
    {
        $now = Clock::now();
        $negative = $this->cache->get($this->key('negative'));
        $negative = is_array($negative) ? array_filter($negative, fn ($expires) => $expires > $now) : [];
        $negative[$kid] = $now + $this->negativeTtl;
        if (count($negative) > self::MAX_NEGATIVE) {
            asort($negative);
            $negative = array_slice($negative, -self::MAX_NEGATIVE, null, true);
        }
        $this->cache->put($this->key('negative'), $negative, $this->negativeTtl);
    }

    private function forgetNegative(array $kids): void
    {
        $negative = $this->cache->get($this->key('negative'));
        if (is_array($negative) && array_intersect_key($negative, array_flip($kids)) !== []) {
            $this->cache->put($this->key('negative'), array_diff_key($negative, array_flip($kids)), $this->negativeTtl);
        }
    }

    private function key(string $suffix): string
    {
        return 'shekel:service_auth:jwks:'.sha1($this->name.'|'.$this->url).':'.$suffix;
    }
}
