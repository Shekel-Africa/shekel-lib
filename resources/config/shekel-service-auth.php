<?php

/*
 * Service-to-service OAuth (spec §10.1). Merged under config('shekel.service_auth'). Set the
 * values through the environment, or add a 'service_auth' array to your config/shekel.php (a
 * top-level key you set there replaces the default below).
 */

$callers = [];
foreach (array_filter(array_map('trim', explode(',', (string) env('SERVICE_AUTH_CALLERS', '')))) as $pair) {
    $parts = array_map('trim', explode('=', $pair, 2));
    if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
        $callers[$parts[0]] = $parts[1];
    }
}

$signatureApiUrl = rtrim((string) env('SIGNATURE_API_URL', ''), '/');

return [
    // Receiving side: this service's scope namespace (e.g. 'loan-api') and logical name. The
    // service name is the `aud` an eligibility snapshot must carry.
    'namespace' => env('SERVICE_AUTH_NAMESPACE'),
    'service_name' => env('SERVICE_AUTH_SERVICE_NAME', env('SERVICE_AUTH_NAMESPACE')),

    // The user-realm OAuth issuer (`iss`) and its JWKS. Fixed URL: never taken from a token.
    'issuer' => env('SERVICE_AUTH_ISSUER'),
    'jwks_url' => env('SERVICE_AUTH_JWKS_URL'),

    // Caller policy (server-owned): OAuth client id => service name. Env format
    // "client-id-1=signature-api,client-id-2=loan-api".
    'callers' => $callers,

    // Calling side: this service's own OAuth client (client_credentials grant).
    'token_endpoint' => env('SERVICE_AUTH_TOKEN_ENDPOINT'),
    'client_id' => env('SERVICE_AUTH_CLIENT_ID'),
    'client_secret' => env('SERVICE_AUTH_CLIENT_SECRET'),
    'token_timeout' => (int) env('SERVICE_AUTH_TOKEN_TIMEOUT', 5),

    // Clock skew accepted on exp/nbf/iat, seconds.
    'leeway' => 60,

    // JWKS cache (applies to the OP JWKS and signature-api's snapshot JWKS).
    'jwks_timeout' => 2,
    'jwks_max_keys' => 16,
    'jwks_cache_ttl' => 3600,
    'jwks_refresh_cooldown' => 60,
    'jwks_negative_ttl' => 300,

    // Cache store for JWKS, tokens, coalescing and locks; null = the default store. It must
    // support atomic locks (redis, database, memcached, dynamodb, file, array).
    'cache_store' => env('SERVICE_AUTH_CACHE_STORE'),

    // signature-api, for eligibility snapshots.
    'signature_api_url' => $signatureApiUrl,
    'signature_api_jwks_url' => env('SIGNATURE_API_JWKS_URL', $signatureApiUrl !== '' ? $signatureApiUrl.'/.well-known/jwks.json' : null),
    'signature_api_issuer' => 'signature-api',
    'signature_api_timeout' => (int) env('SIGNATURE_API_TIMEOUT', 5),

    // Hint handling (spec §10.1): coalescing and refresh age.
    'hint_refresh_after' => 43200,
    'hint_min_fetch_interval' => 10,
    'hint_pending_ttl' => 120,
    'hint_tenant_fetches_per_minute' => 300,
    'hint_queue_connection' => env('SERVICE_AUTH_HINT_QUEUE_CONNECTION'),
    'hint_queue' => env('SERVICE_AUTH_HINT_QUEUE'),
];
