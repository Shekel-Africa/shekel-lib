<?php

/*
 * Restricted (third-party) OAuth clients, S20-4896. Merged under config('shekel.restricted_clients').
 *
 * A user access token issued to a restricted client is refused (403) on every route that declares
 * no scope: a route guarded by sAuth / v3.sAuth alone, or by s2sAuth / v4.s2sAuth / s2sAuth.any
 * without a scope argument. On a route that declares a scope the usual scope check applies.
 * Tokens of every other client (shekel-fe, admin-fe, mobile) and service calls are unaffected.
 *
 * A token is restricted when the guard is on and either
 *  - its verified `aud` (the OAuth client id) is in SHEKEL_RESTRICTED_OAUTH_CLIENTS, or
 *  - it carries the claim client_kind=third_party (issued by authentication-api for clients with
 *    first_party = false, once it does).
 *
 * Turn the guard off with SHEKEL_RESTRICTED_CLIENT_GUARD=false. Add a 'restricted_clients' array to
 * config/shekel.php to override any key.
 */

$enabled = env('SHEKEL_RESTRICTED_CLIENT_GUARD', true);

return [
    // On by default. It only acts on tokens of listed or claimed clients.
    'enabled' => is_bool($enabled) ? $enabled : filter_var($enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,

    // OAuth client ids (the token `aud`), comma separated. Per environment, from Ops.
    'client_ids' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SHEKEL_RESTRICTED_OAUTH_CLIENTS', ''))
    ), fn ($id) => $id !== '')),

    // The claim authentication-api sets on tokens of third-party clients, and its value.
    'claim' => 'client_kind',
    'claim_value' => 'third_party',

    // Extra middleware classes that enforce a scope when given parameters, on top of shekel-lib's
    // s2sAuth classes and Passport's scope middleware. For a service with its own scope check.
    'scope_middleware' => [],
];
