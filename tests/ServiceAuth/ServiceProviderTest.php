<?php

namespace Shekel\ShekelLib\Tests\ServiceAuth;

use Shekel\ShekelLib\ServiceAuth\Middleware\OAuthServiceTokenAuthentication;
use Shekel\ShekelLib\ServiceAuth\ServiceAuthServiceProvider;
use Shekel\ShekelLib\ShekelServiceProvider;
use Shekel\ShekelLib\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_shekel_service_provider_registers_the_service_oauth_alias_and_config(): void
    {
        $this->app->register(ShekelServiceProvider::class);

        $this->assertSame(OAuthServiceTokenAuthentication::class, $this->app['router']->getMiddleware()[ServiceAuthServiceProvider::MIDDLEWARE_ALIAS]);
        $this->assertSame(60, config('shekel.service_auth.jwks_refresh_cooldown'));
        $this->assertSame('signature-api', config('shekel.service_auth.signature_api_issuer'));
        $this->assertSame('loan-api', config('shekel.service_auth.namespace'));
    }
}
