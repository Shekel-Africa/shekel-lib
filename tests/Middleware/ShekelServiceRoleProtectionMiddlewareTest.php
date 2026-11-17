<?php

namespace Shekel\ShekelLib\Tests\Middleware;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Shekel\ShekelLib\Middleware\ShekelServiceRoleProtectionMiddleware;

/**
 * s2s_or_role.required as services register it: bare (service secret only, S20-4646) and with roles
 * (service secret, or a signed-in user holding a role, S20-4631).
 */
class ShekelServiceRoleProtectionMiddlewareTest extends TestCase
{
    private const SECRET = 'messaging-test-secret-value';

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('shekel.messaging-secret', self::SECRET);
        $app['config']->set('shekel.transaction-secret', '');
    }

    protected function defineRoutes($router)
    {
        $router->aliasMiddleware('s2s_or_role.required', ShekelServiceRoleProtectionMiddleware::class);

        Route::post('/bare', fn () => response()->json(['ok' => true]))->middleware('s2s_or_role.required');
        Route::post('/admin-only', fn () => response()->json(['ok' => true]))->middleware('s2s_or_role.required:admin');
    }

    private function service(string $secret, string $name = 'messaging'): array
    {
        return ['s2s' => $secret, 's2sName' => $name, 'Accept' => 'application/json'];
    }

    private function user(string $type): GenericUser
    {
        return new GenericUser(['id' => 1, 'user_type' => $type]);
    }

    // --- no roles: the service secret is the only way in -------------------------------------

    public function test_bare_route_without_secret_is_401(): void
    {
        $this->postJson('/bare')->assertStatus(401);
    }

    public function test_bare_route_with_wrong_secret_is_401_and_the_log_never_holds_a_secret(): void
    {
        Log::spy();

        $this->postJson('/bare', [], $this->service('wrong-secret'))->assertStatus(401);

        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context) {
            $logged = $message.json_encode($context);

            return $message === 'Service secret did not match'
                && $context['s2sName'] === 'messaging'
                && $context['path'] === 'bare'
                && $context['secret_configured'] === true
                && !str_contains($logged, 'wrong-secret')
                && !str_contains($logged, self::SECRET);
        });
    }

    public function test_bare_route_with_empty_configured_secret_is_401(): void
    {
        // transaction-secret is configured as '': neither an empty nor any other s2s may match it.
        $this->postJson('/bare', [], $this->service('', 'transaction'))->assertStatus(401);
        $this->postJson('/bare', [], $this->service('anything', 'transaction'))->assertStatus(401);
    }

    public function test_bare_route_with_unknown_service_name_is_401(): void
    {
        $this->postJson('/bare', [], $this->service(self::SECRET, 'nobody'))->assertStatus(401);
    }

    public function test_bare_route_with_valid_secret_but_no_service_name_is_401(): void
    {
        $this->postJson('/bare', [], ['s2s' => self::SECRET, 'Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_bare_route_refuses_a_signed_in_user_without_secret(): void
    {
        $this->actingAs($this->user('admin'));

        $this->postJson('/bare')->assertStatus(401);
    }

    public function test_bare_route_with_valid_secret_passes(): void
    {
        $this->postJson('/bare', [], $this->service(self::SECRET))->assertOk()->assertJson(['ok' => true]);
    }

    // --- with roles: unchanged (S20-4631) ----------------------------------------------------

    public function test_role_route_with_valid_secret_passes(): void
    {
        $this->postJson('/admin-only', [], $this->service(self::SECRET))->assertOk();
    }

    public function test_role_route_without_secret_or_user_is_401(): void
    {
        $this->postJson('/admin-only')->assertStatus(401);
        $this->postJson('/admin-only', [], $this->service('wrong-secret'))->assertStatus(401);
    }

    public function test_role_route_lets_a_user_with_the_role_in(): void
    {
        $this->actingAs($this->user('admin'));

        $this->postJson('/admin-only')->assertOk();
    }

    public function test_role_route_refuses_a_user_without_the_role_with_403(): void
    {
        $this->actingAs($this->user('customer'));

        $this->postJson('/admin-only')->assertStatus(403);
    }
}
