<?php

namespace Shekel\ShekelLib\Tests\ServiceAuth;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Shekel\ShekelLib\ServiceAuth\Tenant\TenantResolver;
use Shekel\ShekelLib\Tests\Support\TestKeys;
use Shekel\ShekelLib\Tests\Support\Tokens;
use Shekel\ShekelLib\Tests\TestCase;

class OAuthServiceTokenAuthenticationTest extends TestCase
{
    private FakeTenantResolver $tenants;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([self::OP_JWKS => Http::response($this->opJwks())]);
        $this->tenants = new FakeTenantResolver([Tokens::TENANT, '77777777-7777-4777-8777-777777777777']);
        $this->app->instance(TenantResolver::class, $this->tenants);

        $echo = fn (Request $request) => response()->json([
            'caller' => $request->attributes->get('service_caller'),
            'client_id' => $request->attributes->get('service_client_id'),
            'tenant_id' => $request->attributes->get('service_tenant_id'),
            'scopes' => $request->attributes->get('service_scopes'),
        ]);
        Route::post('/hint', $echo)->middleware('service.oauth:loan-api:signature.hint,signature-api');
        Route::post('/hint-default', $echo)->middleware('service.oauth:loan-api:signature.hint')->defaults('service_callers', ['signature-api']);
        Route::post('/no-callers', $echo)->middleware('service.oauth:loan-api:signature.hint');
        Route::post('/tenants/{client_id}/hint', $echo)->middleware('service.oauth:loan-api:signature.hint,signature-api');
        Route::post('/other-scope', $echo)->middleware('service.oauth:loan-api:loan.read,signature-api,transaction-api');
    }

    private function send(?string $token, string $uri = '/hint', array $body = [], array $headers = [])
    {
        if ($token !== null) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $this->postJson($uri, $body, $headers);
    }

    public function test_a_valid_service_token_passes_and_sets_the_request_attributes(): void
    {
        $this->send(Tokens::rs256(Tokens::serviceClaims()), '/hint', ['client_id' => Tokens::TENANT])
            ->assertOk()
            ->assertExactJson([
                'caller' => 'signature-api',
                'client_id' => Tokens::CALLER_CLIENT_ID,
                'tenant_id' => Tokens::TENANT,
                'scopes' => ['loan-api:signature.hint'],
            ]);
        $this->assertSame([Tokens::TENANT], $this->tenants->selected);
    }

    public function test_accepted_callers_can_come_from_the_route_default(): void
    {
        $this->send(Tokens::rs256(Tokens::serviceClaims()), '/hint-default')->assertOk();
    }

    public function test_401_cases(): void
    {
        $rsaPublicPem = TestKeys::rsa()['public'];
        $now = Carbon::now()->getTimestamp();
        $cases = [
            'no token' => null,
            'garbage' => 'not-a-jwt',
            'alg none' => Tokens::unsigned(['alg' => 'none', 'kid' => Tokens::KID, 'typ' => 'JWT'], Tokens::serviceClaims()),
            'alg none, empty signature segment kept' => Tokens::unsigned(['alg' => 'none', 'typ' => 'JWT'], Tokens::serviceClaims()),
            'HS256 random secret' => Tokens::hs256(Tokens::serviceClaims(), str_repeat('s', 64)),
            'HS256 signed with the RSA public key' => Tokens::hs256(Tokens::serviceClaims(), $rsaPublicPem),
            'RS512' => Tokens::unsigned(['alg' => 'RS512', 'kid' => Tokens::KID], Tokens::serviceClaims(), 'x'),
            'ES256 token' => Tokens::es256(Tokens::serviceClaims(), Tokens::KID, null, ['typ' => 'JWT']),
            'missing kid' => Tokens::rs256(Tokens::serviceClaims(), null),
            'unknown kid' => Tokens::rs256(Tokens::serviceClaims(), 'other-kid'),
            'signed by another key with the known kid' => Tokens::rs256(Tokens::serviceClaims(), Tokens::KID, TestKeys::rsa('attacker')),
            'wrong issuer' => Tokens::rs256(Tokens::serviceClaims(['iss' => 'https://evil.example.test'])),
            'missing iss' => Tokens::rs256(Tokens::serviceClaims(['iss' => null])),
            'expired beyond leeway' => Tokens::rs256(Tokens::serviceClaims(['iat' => $now - 1000, 'nbf' => $now - 1000, 'exp' => $now - 61])),
            'nbf in the future beyond leeway' => Tokens::rs256(Tokens::serviceClaims(['nbf' => $now + 61])),
            'iat in the future beyond leeway' => Tokens::rs256(Tokens::serviceClaims(['iat' => $now + 120, 'nbf' => $now])),
            'missing exp' => Tokens::rs256(Tokens::serviceClaims(['exp' => null])),
            'missing tenant_id' => Tokens::rs256(Tokens::serviceClaims(['tenant_id' => null])),
            'empty tenant_id' => Tokens::rs256(Tokens::serviceClaims(['tenant_id' => '  '])),
            'not a service token' => Tokens::rs256(Tokens::serviceClaims(['client_kind' => 'user'])),
            'no client_kind' => Tokens::rs256(Tokens::serviceClaims(['client_kind' => null])),
            'missing aud' => Tokens::rs256(Tokens::serviceClaims(['aud' => null])),
            'typ is an array' => Tokens::rs256(Tokens::serviceClaims(), Tokens::KID, null, ['typ' => []]),
            'snapshot typ' => Tokens::rs256(Tokens::serviceClaims(), Tokens::KID, null, ['typ' => 'eligibility+jwt']),
        ];

        foreach ($cases as $name => $token) {
            $response = $this->send($token);
            $this->assertSame(401, $response->getStatusCode(), $name);
            $this->assertSame('Unauthenticated.', $response->json('message'), $name);
        }
        $this->assertSame([], $this->tenants->selected, 'no tenant is selected for a refused token');
    }

    public function test_the_legacy_s2s_header_is_never_a_fallback(): void
    {
        config(['shekel.signature-api-secret' => 'legacy-secret']);
        $this->send(null, '/hint', [], ['s2s' => 'legacy-secret', 's2sName' => 'signature-api'])->assertStatus(401);
    }

    public function test_leeway_of_60_seconds_is_accepted(): void
    {
        $now = Carbon::now()->getTimestamp();
        $this->send(Tokens::rs256(Tokens::serviceClaims(['iat' => $now - 700, 'nbf' => $now - 700, 'exp' => $now - 59])))->assertOk();
        $this->send(Tokens::rs256(Tokens::serviceClaims(['iat' => $now + 59, 'nbf' => $now + 59])))->assertOk();
    }

    public function test_403_cases(): void
    {
        $cases = [
            'another namespace\'s scope only' => [Tokens::serviceClaims(['scopes' => ['transaction-api:signature.hint']]), '/hint', []],
            'namespace scope but not the endpoint scope' => [Tokens::serviceClaims(['scopes' => ['loan-api:loan.read']]), '/hint', []],
            'no scopes' => [Tokens::serviceClaims(['scopes' => []]), '/hint', []],
            'wildcard' => [Tokens::serviceClaims(['scopes' => ['*', 'loan-api:signature.hint']]), '/hint', []],
            'caller not in policy' => [Tokens::serviceClaims(['aud' => '99999999-9999-4999-8999-999999999999']), '/hint', []],
            'caller in policy but not accepted by the endpoint' => [Tokens::serviceClaims(['aud' => '55555555-5555-4555-8555-555555555555']), '/hint', []],
            'endpoint names no callers' => [Tokens::serviceClaims(), '/no-callers', []],
            'body client_id differs from tenant_id' => [Tokens::serviceClaims(), '/hint', ['client_id' => '77777777-7777-4777-8777-777777777777']],
            'body client_id not a string' => [Tokens::serviceClaims(), '/hint', ['client_id' => [Tokens::TENANT]]],
            'route client_id differs' => [Tokens::serviceClaims(), '/tenants/77777777-7777-4777-8777-777777777777/hint', []],
            'query client_id differs' => [Tokens::serviceClaims(), '/hint?client_id=77777777-7777-4777-8777-777777777777', []],
            'unknown tenant' => [Tokens::serviceClaims(['tenant_id' => '88888888-8888-4888-8888-888888888888']), '/hint', []],
        ];

        foreach ($cases as $name => [$claims, $uri, $body]) {
            $response = $this->send(Tokens::rs256($claims), $uri, $body);
            $this->assertSame(403, $response->getStatusCode(), $name);
        }
        $this->assertSame([], $this->tenants->selected);
    }

    public function test_a_matching_route_client_id_passes(): void
    {
        $this->send(Tokens::rs256(Tokens::serviceClaims()), '/tenants/'.Tokens::TENANT.'/hint', ['client_id' => Tokens::TENANT])->assertOk();
    }

    public function test_a_tenant_lookup_failure_is_503_not_a_guess(): void
    {
        $this->tenants->failing = true;
        $this->send(Tokens::rs256(Tokens::serviceClaims()))->assertStatus(503);
    }

    public function test_the_jwks_is_fetched_once_across_requests(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->send(Tokens::rs256(Tokens::serviceClaims()))->assertOk();
        }
        Http::assertSentCount(1);
    }
}

class FakeTenantResolver implements TenantResolver
{
    public array $selected = [];
    public bool $failing = false;

    public function __construct(private array $known)
    {
    }

    public function isKnown(string $tenantId): bool
    {
        if ($this->failing) {
            throw new \RuntimeException('auth-api down');
        }

        return in_array($tenantId, $this->known, true);
    }

    public function select(string $tenantId): void
    {
        $this->selected[] = $tenantId;
    }
}
