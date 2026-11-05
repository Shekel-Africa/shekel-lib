<?php

namespace Shekel\ShekelLib\ServiceAuth\Client;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Client\Factory as HttpFactory;
use InvalidArgumentException;
use Throwable;

/**
 * Acquires OAuth client_credentials tokens for calls to other services (spec §10.1).
 *
 * - One token per (sorted scope set, tenant), cached (encrypted) until 60s before it expires.
 * - Scopes must name exactly one target namespace, and never `*`; a tenant is always required.
 * - withRetryOn401() refreshes the token once when the receiver rejects it.
 * - Tokens and the client secret are never logged or put in exception messages.
 */
class ServiceTokenProvider
{
    private const EXPIRY_MARGIN = 60;

    public function __construct(
        private HttpFactory $http,
        private CacheRepository $cache,
        private Encrypter $encrypter,
        private string $tokenEndpoint,
        private string $clientId,
        private string $clientSecret,
        private int $timeout = 5,
    ) {
    }

    /**
     * A bearer token carrying $scopes for $tenantId.
     *
     * @param string[] $scopes e.g. ['signature-api:eligibility.read']
     *
     * @throws ServiceTokenException
     */
    public function token(array $scopes, string $tenantId): string
    {
        $scopes = self::normalizeScopes($scopes);
        if (trim($tenantId) === '') {
            throw new InvalidArgumentException('A service token needs a tenant.');
        }

        $key = $this->cacheKey($scopes, $tenantId);
        $cached = $this->cache->get($key);
        if (is_string($cached)) {
            try {
                return $this->encrypter->decrypt($cached, false);
            } catch (Throwable $e) {
                $this->cache->forget($key);
            }
        }

        return $this->issue($scopes, $tenantId, $key);
    }

    /**
     * Drop the cached token for (scopes, tenant), so the next token() asks the OP.
     */
    public function forget(array $scopes, string $tenantId): void
    {
        $this->cache->forget($this->cacheKey(self::normalizeScopes($scopes), $tenantId));
    }

    /**
     * Run $call with a token; if its response is a 401, refresh the token once and run it again.
     *
     * @param callable(string $token): mixed $call returns a response with status() (e.g. an
     *                                            Illuminate\Http\Client\Response)
     */
    public function withRetryOn401(array $scopes, string $tenantId, callable $call)
    {
        $response = $call($this->token($scopes, $tenantId));
        if (is_object($response) && method_exists($response, 'status') && $response->status() === 401) {
            $this->forget($scopes, $tenantId);
            $response = $call($this->token($scopes, $tenantId));
        }

        return $response;
    }

    /**
     * @return string[] sorted, unique, one namespace
     */
    public static function normalizeScopes(array $scopes): array
    {
        $scopes = array_values(array_unique(array_map('strval', $scopes)));
        sort($scopes, SORT_STRING);
        if ($scopes === []) {
            throw new InvalidArgumentException('A service token needs at least one scope.');
        }
        $namespaces = [];
        foreach ($scopes as $scope) {
            if ($scope === '*' || !preg_match('/^([a-z0-9-]+):[A-Za-z0-9._-]+$/', $scope, $match)) {
                throw new InvalidArgumentException("Invalid service scope '$scope'.");
            }
            $namespaces[$match[1]] = true;
        }
        if (count($namespaces) !== 1) {
            throw new InvalidArgumentException('A service token names scopes from one target namespace only.');
        }

        return $scopes;
    }

    private function issue(array $scopes, string $tenantId, string $key): string
    {
        if ($this->tokenEndpoint === '' || $this->clientId === '' || $this->clientSecret === '') {
            throw new ServiceTokenException('Service auth client is not configured.');
        }

        try {
            $response = $this->http
                ->asForm()
                ->acceptJson()
                ->timeout($this->timeout)
                ->withOptions(['allow_redirects' => false])
                ->post($this->tokenEndpoint, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => implode(' ', $scopes),
                    'tenant_id' => $tenantId,
                ]);
        } catch (Throwable $e) {
            throw new ServiceTokenException('Token endpoint unreachable.');
        }

        if (!$response->successful()) {
            // Only a known RFC 6749 error code is echoed; any other response text is discarded.
            $error = $response->json('error');
            $known = ['invalid_request', 'invalid_client', 'invalid_grant', 'unauthorized_client', 'unsupported_grant_type', 'invalid_scope'];
            throw new ServiceTokenException('Token endpoint answered '.$response->status().(in_array($error, $known, true) ? " ($error)" : '').'.');
        }
        $token = $response->json('access_token');
        if (!is_string($token) || $token === '') {
            throw new ServiceTokenException('Token endpoint returned no access token.');
        }

        $ttl = (int) $response->json('expires_in', 0) - self::EXPIRY_MARGIN;
        if ($ttl > 0) {
            $this->cache->put($key, $this->encrypter->encrypt($token, false), $ttl);
        }

        return $token;
    }

    private function cacheKey(array $scopes, string $tenantId): string
    {
        return 'shekel:service_auth:token:'.hash('sha256', $this->tokenEndpoint.'|'.$this->clientId.'|'.$tenantId.'|'.implode(' ', $scopes));
    }
}
