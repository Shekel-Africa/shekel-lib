<?php

namespace Shekel\ShekelLib\Tests;

use Illuminate\Support\Carbon;
use Orchestra\Testbench\TestCase as Orchestra;
use Shekel\ShekelLib\ServiceAuth\ServiceAuthServiceProvider;
use Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjectionStore;
use Shekel\ShekelLib\Tests\Support\InMemoryProjectionStore;
use Shekel\ShekelLib\Tests\Support\TestKeys;
use Shekel\ShekelLib\Tests\Support\Tokens;

abstract class TestCase extends Orchestra
{
    public const OP_JWKS = 'https://auth.example.test/oauth/jwks';
    public const TOKEN_ENDPOINT = 'https://auth.example.test/oauth/token';
    public const SIGNATURE_API = 'https://signature-api.example.test';
    public const SNAPSHOT_JWKS = 'https://signature-api.example.test/.well-known/jwks.json';
    public const SNAPSHOT_KID = 'snap-key-1';
    public const SUBJECT = '33333333-3333-4333-8333-333333333333';
    public const SIGNATURE_API_CLIENT_ID = '44444444-4444-4444-8444-444444444444';

    protected InMemoryProjectionStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::createFromTimestamp(1_800_000_000));
        $this->store = new InMemoryProjectionStore();
        $this->app->instance(EligibilityProjectionStore::class, $this->store);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [ServiceAuthServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('shekel.service_auth', array_merge($app['config']->get('shekel.service_auth', []), [
            'namespace' => 'loan-api',
            'service_name' => 'loan-api',
            'issuer' => Tokens::ISSUER,
            'jwks_url' => self::OP_JWKS,
            'callers' => [
                Tokens::CALLER_CLIENT_ID => 'signature-api',
                '55555555-5555-4555-8555-555555555555' => 'transaction-api',
            ],
            'token_endpoint' => self::TOKEN_ENDPOINT,
            'client_id' => '66666666-6666-4666-8666-666666666666',
            'client_secret' => 'test-secret-value',
            'signature_api_url' => self::SIGNATURE_API,
            'signature_api_jwks_url' => self::SNAPSHOT_JWKS,
        ]));
    }

    protected function opJwks(array $extraKeys = []): array
    {
        return ['keys' => array_merge([TestKeys::jwk(TestKeys::rsa(), Tokens::KID, false)], $extraKeys)];
    }

    protected function snapshotJwks(): array
    {
        return ['keys' => [TestKeys::jwk(TestKeys::ec(), self::SNAPSHOT_KID)]];
    }

    protected function snapshotClaims(array $overrides = [], ?array $actions = null): array
    {
        $now = Carbon::now()->getTimestamp();
        $evaluatedAt = $overrides['evaluated_at'] ?? $now;

        return array_merge([
            'iss' => 'signature-api',
            'aud' => 'loan-api',
            'sub' => self::SUBJECT,
            'client_id' => Tokens::TENANT,
            'subject_revision' => 5,
            'evaluated_at' => $evaluatedAt,
            'iat' => $now,
            'exp' => $evaluatedAt + 86400,
            'jti' => 'snap-'.bin2hex(random_bytes(4)),
            'snapshot' => [
                'actions' => $actions ?? [
                    'loan.create' => ['eligible' => true, 'valid_until' => null, 'reasons' => [], 'basis' => [['obligation_id' => 'o-1', 'revision' => 1]]],
                ],
                'role' => 'local_dealer',
                'enforcement_state' => 'enforce',
                'enforcement_revision' => 2,
            ],
        ], $overrides);
    }
}
