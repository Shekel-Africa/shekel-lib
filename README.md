# SHEKEL LIB
> A Laravel Package to connect Services

## Installation

[PHP](https://php.net) 8.0+ and [Composer](https://getcomposer.org) are required. Supports Laravel 9 through 13.

Add this to your composer.json

```
"repositories": {
    "dev-package": {
        "type": "vcs",
        "url": "git@github.com:Shekel-Africa/shekel-lib.git"
    }
},
```
Then require a tagged release (versions are the repository's git tags), for example:
```json
"require": {
    "shekel/shekel-lib": "^1.96.145"
}
```
Avoid `dev-master`: it follows the moving `master` branch, so installs are not reproducible.

## Service-to-service auth (OAuth service tokens)

`Shekel\ShekelLib\ServiceAuth` verifies and obtains OAuth `client_credentials` service tokens issued by
authentication-api, and gives consumers of signature-api the hint, snapshot and guard helpers (e-signature
spec §10.1, §10.2). Tokens are verified locally against the OP's JWKS.

Configuration is under `config('shekel.service_auth')` (defaults: `resources/config/shekel-service-auth.php`,
merged automatically). Set it through the environment, or publish it with
`php artisan vendor:publish --tag=shekel-service-auth` (a published `config/shekel-service-auth.php` overrides the
defaults). Key names: `SERVICE_AUTH_NAMESPACE`, `SERVICE_AUTH_SERVICE_NAME`, `SERVICE_AUTH_ISSUER`,
`SERVICE_AUTH_JWKS_URL`, `SERVICE_AUTH_CALLERS` (`client-id=service-name,...`), `SERVICE_AUTH_TOKEN_ENDPOINT`,
`SERVICE_AUTH_CLIENT_ID`, `SERVICE_AUTH_CLIENT_SECRET`, `SIGNATURE_API_URL`, `SIGNATURE_API_JWKS_URL`,
`SERVICE_AUTH_CACHE_STORE` (must support locks).

Receiving a service call (the first parameter is the required scope, the rest are the accepted callers;
or set the route default `service_callers`):

```php
Route::post('/v3/internal/signatures/hint', fn (Request $r, HintReceiver $h) => $h->receive($r))
    ->middleware('service.oauth:loan-api:signature.hint,signature-api');
```

401 = no, invalid or unverifiable token (or missing `iss`/`kid`/`tenant_id`); 403 = scope, caller, `*`, unknown
tenant or `client_id` different from the token's `tenant_id`. On success the tenant is selected (bind your own
`ServiceAuth\Tenant\TenantResolver` to change how) and the request attributes `service_caller`,
`service_client_id`, `service_tenant_id` and `service_scopes` are set.

Calling another service: `app(ServiceTokenProvider::class)->withRetryOn401(['signature-api:trigger.fire'],
$tenantId, fn (string $token) => Http::withToken($token)->post(...))`. Scrub logs with
`ServiceAuth\Client\RedactsAuthorization` (a Monolog processor and a Sentry `before_send`).

Consumers of eligibility bind `ServiceAuth\Snapshot\EligibilityProjectionStore` and use `Consumer\HintReceiver`,
`Consumer\SnapshotFetcher` and `Consumer\EligibilityGuard::check($clientId, $subjectId, $action, 'new_credit'|'servicing')`.
Listen to `Events\EligibilitySnapshotConflict` and `Events\EligibilitySnapshotRejected` for alerts.

## Tests

The suite (PHPUnit + Orchestra Testbench) needs no network or database; keys are generated with openssl.

```bash
composer install
composer test            # or vendor/bin/phpunit
```

`composer.lock` resolves for the lowest supported stack (PHP 8.0, Laravel 9). To run it there with Docker:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.0-cli vendor/bin/phpunit
```
