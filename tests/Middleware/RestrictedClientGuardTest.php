<?php

namespace Shekel\ShekelLib\Tests\Middleware;

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Mockery;
use Shekel\ShekelLib\Middleware\CheckAnyServiceAuthentication;
use Shekel\ShekelLib\Middleware\ServiceAuthentication;
use Shekel\ShekelLib\Middleware\ShekelAuthMiddleware;
use Shekel\ShekelLib\Middleware\ShekelServiceRoleProtectionMiddleware;
use Shekel\ShekelLib\Middleware\ShekelServiceSecretMiddleware;
use Shekel\ShekelLib\Middleware\v3\ServiceAuthentication as V3ServiceAuthentication;
use Shekel\ShekelLib\Middleware\v3\ShekelAuthMiddleware as V3ShekelAuthMiddleware;
use Shekel\ShekelLib\Services\AuthService as LegacyAuthService;
use Shekel\ShekelLib\Services\v3\AuthService;
use Shekel\ShekelLib\Tests\Support\Tokens;
use Shekel\ShekelLib\Tests\TestCase;
use Shekel\ShekelLib\Utils\RestrictedClientGuard;

/**
 * S20-4896: a token issued to a restricted (third-party) OAuth client is refused on any route that
 * declares no scope, and is held to the declared scope elsewhere. First-party tokens and service
 * calls are unchanged.
 */
class RestrictedClientGuardTest extends TestCase
{
    private const SHAILOQ = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    private const OTHER_THIRD_PARTY = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
    private const FIRST_PARTY = '7';
    private const USER_ID = '88888888-8888-4888-8888-888888888888';
    private const SECRET = 'messaging-test-secret-value';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([self::OP_JWKS => Http::response($this->opJwks())]);

        // authentication-api's /authenticated, as sAuth and v3.sAuth call it.
        $authenticated = fn () => new ClientResponse(new Psr7Response(200, ['Content-Type' => 'application/json'], json_encode([
            'data' => ['id' => self::USER_ID, 'user_type' => 'local_dealer', 'kyc_complete' => true],
        ])));
        foreach ([AuthService::class, LegacyAuthService::class] as $service) {
            $mock = Mockery::mock($service);
            $mock->shouldReceive('setToken');
            $mock->shouldReceive('getAuthenticated')->andReturnUsing($authenticated);
            $this->app->instance($service, $mock);
        }
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('shekel.restricted_clients.client_ids', [self::SHAILOQ]);
        $app['config']->set('shekel.messaging-secret', self::SECRET);
    }

    protected function defineRoutes($router)
    {
        // Aliases as the services register them in their Kernels.
        $router->aliasMiddleware('sAuth', ShekelAuthMiddleware::class);
        $router->aliasMiddleware('v3.sAuth', V3ShekelAuthMiddleware::class);
        $router->aliasMiddleware('s2sAuth', ServiceAuthentication::class);
        $router->aliasMiddleware('v4.s2sAuth', V3ServiceAuthentication::class);
        $router->aliasMiddleware('s2sAuth.any', CheckAnyServiceAuthentication::class);
        $router->aliasMiddleware('s2s.required', ShekelServiceSecretMiddleware::class);
        $router->aliasMiddleware('s2s_or_role.required', ShekelServiceRoleProtectionMiddleware::class);

        $ok = fn () => response()->json(['ok' => true]);

        // No scope anywhere on the route.
        Route::post('/car/purchase', $ok)->middleware('v3.sAuth');
        Route::post('/legacy/purchase', $ok)->middleware('sAuth');
        Route::get('/notifications', $ok)->middleware(['v3.sAuth', 's2sAuth']);
        Route::get('/v4-bare', $ok)->middleware('v4.s2sAuth');
        Route::get('/s2s-bare', $ok)->middleware('s2sAuth');
        Route::get('/any-bare', $ok)->middleware('s2sAuth.any');

        // A scope declared on the route itself, or on an enclosing group.
        Route::get('/wallet', $ok)->middleware(['v3.sAuth', 'v4.s2sAuth:transaction.read']);
        Route::group(['middleware' => ['v3.sAuth', 's2sAuth:loan.read']], function () use ($ok) {
            Route::group(['middleware' => ['s2sAuth']], function () use ($ok) {
                Route::get('/loans', $ok);
            });
        });
        Route::get('/limit', $ok)->middleware(['v3.sAuth', 's2sAuth.any:loan.read,loan.limit.read']);

        // Service-to-service.
        Route::post('/send/otp-email', $ok)->middleware('s2s.required');
        Route::post('/send/alert', $ok)->middleware('s2s_or_role.required');
    }

    private function token(string $clientId, array $scopes = [], array $extra = []): string
    {
        $now = Carbon::now()->getTimestamp();

        return Tokens::rs256(array_merge([
            'aud' => $clientId,
            'jti' => 'jti-'.bin2hex(random_bytes(4)),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 3600,
            'sub' => self::USER_ID,
            'scopes' => $scopes,
        ], $extra), null);
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    // --- third-party client, no scope on the route: refused ------------------------------------

    public function test_a_listed_third_party_token_is_refused_on_a_route_without_a_scope(): void
    {
        // The deprecations channel (newer PHP on an older Laravel) logs through the spy too.
        Log::spy()->shouldReceive('channel')->andReturnSelf();
        $token = $this->token(self::SHAILOQ, ['transaction.read', 'transaction.write']);

        $this->postJson('/car/purchase', [], $this->bearer($token))
            ->assertStatus(403)
            ->assertExactJson(['message' => RestrictedClientGuard::MESSAGE]);

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) use ($token) {
            return $message === 'restricted_client.refused'
                && $context === ['client_id' => self::SHAILOQ, 'method' => 'POST', 'path' => 'car/purchase']
                && !str_contains(json_encode($context), $token);
        });
    }

    public function test_a_token_with_the_third_party_claim_is_refused_even_when_its_client_is_not_listed(): void
    {
        $token = $this->token(self::OTHER_THIRD_PARTY, ['transaction.read'], ['client_kind' => 'third_party']);

        $this->postJson('/car/purchase', [], $this->bearer($token))->assertStatus(403);
    }

    public function test_every_unscoped_guard_refuses_a_third_party_token(): void
    {
        $token = $this->token(self::SHAILOQ, ['loan.read']);

        $this->postJson('/legacy/purchase', [], $this->bearer($token))->assertStatus(403);
        $this->getJson('/notifications', $this->bearer($token))->assertStatus(403);
        $this->getJson('/v4-bare', $this->bearer($token))->assertStatus(403);
    }

    public function test_the_cached_v3_sauth_path_still_refuses(): void
    {
        $token = $this->token(self::SHAILOQ);

        $this->postJson('/car/purchase', [], $this->bearer($token))->assertStatus(403);
        $this->postJson('/car/purchase', [], $this->bearer($token))->assertStatus(403);
    }

    // --- third-party client, scope on the route: the scope decides -----------------------------

    public function test_a_third_party_token_holding_the_routes_scope_is_allowed(): void
    {
        $this->getJson('/wallet', $this->bearer($this->token(self::SHAILOQ, ['transaction.read'])))->assertOk();
        $this->getJson('/limit', $this->bearer($this->token(self::SHAILOQ, ['loan.limit.read'])))->assertOk();
    }

    public function test_a_scope_on_an_enclosing_group_counts_for_a_bare_inner_middleware(): void
    {
        $this->getJson('/loans', $this->bearer($this->token(self::SHAILOQ, ['loan.read'])))->assertOk();
    }

    public function test_a_third_party_token_lacking_the_routes_scope_is_refused(): void
    {
        $token = $this->token(self::SHAILOQ, ['loan.read']);

        $this->getJson('/wallet', $this->bearer($token))
            ->assertStatus(403)
            ->assertJson(['message' => 'User does not have required permission']);
        $this->getJson('/limit', $this->bearer($this->token(self::SHAILOQ, ['car.read'])))->assertStatus(403);
    }

    // --- first-party clients and services: unchanged ------------------------------------------

    public function test_a_first_party_token_is_unchanged(): void
    {
        $token = $this->token(self::FIRST_PARTY, ['loan.read']);

        $this->postJson('/car/purchase', [], $this->bearer($token))->assertOk();
        $this->postJson('/legacy/purchase', [], $this->bearer($token))->assertOk();
        $this->getJson('/notifications', $this->bearer($token))->assertOk();
        $this->getJson('/v4-bare', $this->bearer($token))->assertOk();
        $this->getJson('/loans', $this->bearer($token))->assertOk();
        // And still held to the scopes it lacks.
        $this->getJson('/wallet', $this->bearer($token))->assertStatus(403);
    }

    public function test_a_service_token_is_unchanged(): void
    {
        $token = Tokens::rs256(Tokens::serviceClaims(), Tokens::KID);

        $this->getJson('/v4-bare', $this->bearer($token))->assertOk();
    }

    public function test_service_secret_routes_are_unchanged_even_with_a_third_party_token_attached(): void
    {
        $headers = $this->bearer($this->token(self::SHAILOQ)) + ['s2s' => self::SECRET, 's2sName' => 'messaging'];

        $this->postJson('/send/otp-email', [], $headers)->assertOk();
        $this->postJson('/send/alert', [], $headers)->assertOk();
        // Without the secret a bare s2s_or_role.required route stays closed to every user token (S20-4646).
        $this->postJson('/send/alert', [], $this->bearer($this->token(self::FIRST_PARTY)))->assertStatus(401);
    }

    // --- rollout flag and config -------------------------------------------------------------

    public function test_the_guard_can_be_turned_off(): void
    {
        config(['shekel.restricted_clients.enabled' => false]);

        $this->postJson('/car/purchase', [], $this->bearer($this->token(self::SHAILOQ)))->assertOk();
    }

    public function test_the_flag_reads_string_values_from_the_environment(): void
    {
        config(['shekel.restricted_clients.enabled' => 'false']);
        $this->assertFalse(RestrictedClientGuard::enabled());

        config(['shekel.restricted_clients.enabled' => 'true']);
        $this->assertTrue(RestrictedClientGuard::enabled());

        config(['shekel.restricted_clients.enabled' => null]);
        $this->assertTrue(RestrictedClientGuard::enabled());
    }

    public function test_an_empty_client_list_restricts_only_claimed_tokens(): void
    {
        config(['shekel.restricted_clients.client_ids' => '']);

        $this->postJson('/car/purchase', [], $this->bearer($this->token(self::SHAILOQ)))->assertOk();
        $this->postJson('/car/purchase', [], $this->bearer($this->token(self::SHAILOQ, [], ['client_kind' => 'third_party'])))
            ->assertStatus(403);
    }

    public function test_the_client_list_accepts_a_comma_separated_string_and_an_audience_array(): void
    {
        config(['shekel.restricted_clients.client_ids' => ' '.self::OTHER_THIRD_PARTY.' , '.self::SHAILOQ]);

        $this->assertTrue(RestrictedClientGuard::isRestricted(['aud' => [self::SHAILOQ]]));
        $this->assertTrue(RestrictedClientGuard::isRestricted(['aud' => self::OTHER_THIRD_PARTY]));
        $this->assertFalse(RestrictedClientGuard::isRestricted(['aud' => self::FIRST_PARTY]));
        $this->assertFalse(RestrictedClientGuard::isRestricted(['aud' => self::FIRST_PARTY, 'client_kind' => 'service']));
        $this->assertFalse(RestrictedClientGuard::isRestricted([]));
    }

    public function test_the_default_config_is_merged_by_the_service_provider(): void
    {
        $this->assertTrue(config('shekel.restricted_clients.enabled'));
        $this->assertSame('client_kind', config('shekel.restricted_clients.claim'));
        $this->assertSame('third_party', config('shekel.restricted_clients.claim_value'));
    }

    public function test_a_request_without_a_route_or_token_is_never_refused(): void
    {
        $this->assertFalse(RestrictedClientGuard::refuses(Request::create('/x')));

        $request = Request::create('/x');
        $request->headers->set('Authorization', 'Bearer not-a-jwt');
        $this->assertFalse(RestrictedClientGuard::refuses($request));
    }

    public function test_the_bare_s2s_guards_alone_refuse_a_third_party_token(): void
    {
        $token = $this->token(self::SHAILOQ, ['loan.read']);

        $this->getJson('/s2s-bare', $this->bearer($token))->assertStatus(403);
        $this->getJson('/any-bare', $this->bearer($token))->assertStatus(403);
    }

    public function test_nothing_is_restricted_while_the_guard_is_disabled(): void
    {
        config(['shekel.restricted_clients.enabled' => false]);

        $this->assertFalse(RestrictedClientGuard::isRestricted(['aud' => self::SHAILOQ, 'client_kind' => 'third_party']));
    }

    public function test_a_request_without_a_route_declares_no_scope(): void
    {
        $this->assertFalse(RestrictedClientGuard::routeDeclaresScope(Request::create('/x')));
    }

    public function test_a_router_failure_while_gathering_middleware_declares_no_scope(): void
    {
        $router = Mockery::mock(\Illuminate\Routing\Router::class);
        $router->shouldReceive('gatherRouteMiddleware')->andThrow(new \RuntimeException('boom'));
        $this->app->instance('router', $router);

        $request = Request::create('/x');
        $request->setRouteResolver(fn () => new \Illuminate\Routing\Route(['GET'], '/x', fn () => null));

        $this->assertFalse(RestrictedClientGuard::routeDeclaresScope($request));
    }

    public function test_tokens_that_are_not_decodable_jwts_have_no_claims(): void
    {
        $this->assertNull(RestrictedClientGuard::claims('a.!!!.c'));
    }
}
