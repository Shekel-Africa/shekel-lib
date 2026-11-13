<?php

namespace Shekel\ShekelLib\ServiceAuth;

use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Shekel\ShekelLib\ServiceAuth\Client\ServiceTokenProvider;
use Shekel\ShekelLib\ServiceAuth\Consumer\EligibilityGuard;
use Shekel\ShekelLib\ServiceAuth\Consumer\HintReceiver;
use Shekel\ShekelLib\ServiceAuth\Consumer\SnapshotFetcher;
use Shekel\ShekelLib\ServiceAuth\Jwks\JwksKeySet;
use Shekel\ShekelLib\ServiceAuth\Middleware\OAuthServiceTokenAuthentication;
use Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilityProjectionStore;
use Shekel\ShekelLib\ServiceAuth\Snapshot\EligibilitySnapshotVerifier;
use Shekel\ShekelLib\ServiceAuth\Snapshot\ProjectionApplier;
use Shekel\ShekelLib\ServiceAuth\Tenant\ClientRepositoryTenantResolver;
use Shekel\ShekelLib\ServiceAuth\Tenant\TenantResolver;
use Shekel\ShekelLib\ServiceAuth\Token\ServiceTokenVerifier;

/**
 * Wires the service-auth classes from config('shekel.service_auth') and registers the
 * service.oauth middleware alias. Registered by ShekelServiceProvider; it can also be registered
 * on its own. Consumers bind EligibilityProjectionStore (and may rebind TenantResolver).
 */
class ServiceAuthServiceProvider extends ServiceProvider
{
    public const MIDDLEWARE_ALIAS = 'service.oauth';

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../resources/config/shekel-service-auth.php', ServiceAuthConfig::KEY);
        // A published config/shekel-service-auth.php overrides both.
        $config = $this->app->make('config');
        if (is_array($published = $config->get('shekel-service-auth'))) {
            $config->set(ServiceAuthConfig::KEY, array_merge((array) $config->get(ServiceAuthConfig::KEY, []), $published));
        }

        $this->app->bindIf(TenantResolver::class, ClientRepositoryTenantResolver::class);
        $this->app->bindIf(CallerPolicy::class, fn () => CallerPolicy::fromConfig());

        $this->app->bind(ServiceTokenVerifier::class, function (Container $app) {
            return new ServiceTokenVerifier(
                $this->keySet($app, 'op', ServiceAuthConfig::string('jwks_url'), 'RS256'),
                ServiceAuthConfig::string('issuer'),
                ServiceAuthConfig::int('leeway', 60),
            );
        });

        $this->app->bind(EligibilitySnapshotVerifier::class, function (Container $app) {
            return new EligibilitySnapshotVerifier(
                $this->keySet($app, 'signature-api', ServiceAuthConfig::string('signature_api_jwks_url'), 'ES256'),
                ServiceAuthConfig::string('signature_api_issuer'),
                ServiceAuthConfig::string('service_name'),
                ServiceAuthConfig::int('leeway', 60),
            );
        });

        $this->app->bind(ServiceTokenProvider::class, function (Container $app) {
            return new ServiceTokenProvider(
                $app->make(HttpFactory::class),
                $this->cache($app),
                $app->make(Encrypter::class),
                ServiceAuthConfig::string('token_endpoint'),
                ServiceAuthConfig::string('client_id'),
                ServiceAuthConfig::string('client_secret'),
                ServiceAuthConfig::int('token_timeout', 5),
            );
        });

        $this->app->bind(ProjectionApplier::class, function (Container $app) {
            return new ProjectionApplier($app->make(EligibilityProjectionStore::class), $this->cache($app), $app->make(Dispatcher::class));
        });

        $this->app->bind(SnapshotFetcher::class, function (Container $app) {
            return new SnapshotFetcher(
                $app->make(ServiceTokenProvider::class),
                $app->make(HttpFactory::class),
                $app->make(EligibilitySnapshotVerifier::class),
                $app->make(ProjectionApplier::class),
                $this->cache($app),
                $app->make(Dispatcher::class),
                rtrim(ServiceAuthConfig::string('signature_api_url'), '/'),
                ServiceAuthConfig::int('signature_api_timeout', 5),
            );
        });

        $this->app->bind(HintReceiver::class, function (Container $app) {
            return new HintReceiver(
                $app->make(EligibilityProjectionStore::class),
                $this->cache($app),
                $app->make(BusDispatcher::class),
                ServiceAuthConfig::int('hint_refresh_after', 43200),
                ServiceAuthConfig::int('hint_min_fetch_interval', 10),
                ServiceAuthConfig::int('hint_pending_ttl', 120),
                ServiceAuthConfig::int('hint_tenant_fetches_per_minute', 300),
                ServiceAuthConfig::string('hint_queue_connection') ?: null,
                ServiceAuthConfig::string('hint_queue') ?: null,
            );
        });

        $this->app->bind(EligibilityGuard::class, function (Container $app) {
            return new EligibilityGuard($app->make(EligibilityProjectionStore::class), $app->make(SnapshotFetcher::class));
        });
    }

    public function boot()
    {
        $this->app['router']->aliasMiddleware(self::MIDDLEWARE_ALIAS, OAuthServiceTokenAuthentication::class);

        $this->publishes([
            __DIR__.'/../../resources/config/shekel-service-auth.php' => config_path('shekel-service-auth.php'),
        ], 'shekel-service-auth');
    }

    private function keySet(Container $app, string $name, string $url, string $algorithm): JwksKeySet
    {
        return new JwksKeySet(
            $url,
            $algorithm,
            $this->cache($app),
            $app->make(HttpFactory::class),
            $name,
            ServiceAuthConfig::int('jwks_timeout', 2),
            ServiceAuthConfig::int('jwks_max_keys', 16),
            ServiceAuthConfig::int('jwks_cache_ttl', 3600),
            ServiceAuthConfig::int('jwks_refresh_cooldown', 60),
            ServiceAuthConfig::int('jwks_negative_ttl', 300),
        );
    }

    private function cache(Container $app): CacheRepository
    {
        $store = ServiceAuthConfig::string('cache_store');

        return $app->make(CacheFactory::class)->store($store !== '' ? $store : null);
    }
}
