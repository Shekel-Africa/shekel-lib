<?php

namespace Shekel\ShekelLib;

use Carbon\Laravel\ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Opcodes\LogViewer\LogViewerServiceProvider;
use PragmaRX\Yaml\Package\Yaml;
use Illuminate\Contracts\Foundation\Application;
use Shekel\ShekelLib\Repositories\ClientRepository;
use Shekel\ShekelLib\Services\v3\ShekelBaseService;
use Shekel\ShekelLib\Utils\TenantClient;

class ShekelServiceProvider extends ServiceProvider {

    public function boot() {
        $config = realpath(__DIR__.'/../resources/config/shekel.php');
        $config2 = realpath(__DIR__.'/../resources/config/tenant.php');
        $config3 = realpath(__DIR__.'/../resources/config/amplitude.php');
        $config4 = realpath(__DIR__.'/../resources/config/n8nworkflow.php');
        $this->publishes([
            $config => config_path('shekel.php'),
            $config2 => config_path('tenant.php'),
            $config3 => config_path('amplitude.php'),
            $config4 => config_path('n8nworkflow.php'),
        ], 'laravel-assets');
        $this->publishes(self::pathsToPublish(LogViewerServiceProvider::class), 'shekel-deps');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shekel-lib');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/shekel-lib'),
        ], 'laravel-assets');

        $this->app->singleton(ClientRepository::class);

        if (TenantClient::getDefaultClientId() !== null) {
            Queue::createPayloadUsing(function () {
                return [
                    'client_id' => TenantClient::getClientId(),
                    'client_connection' => TenantClient::getTenantConnection()
                ];
            });
            Queue::before(function (JobProcessing $jobProcessing) {
                $tenantId = $jobProcessing->job->payload()['client_id'];
                $clientConnection = $jobProcessing->job->payload()['client_connection'];
                TenantClient::setClientId($tenantId ?? '');
                TenantClient::setClientConnection($clientConnection);
            });
        }


        $services = [
            \Shekel\ShekelLib\Services\v4\StoreService::class,
            \Shekel\ShekelLib\Services\v3\StoreService::class,
            \Shekel\ShekelLib\Services\v3\MessagingService::class,
            \Shekel\ShekelLib\Services\v3\CarService::class,
            \Shekel\ShekelLib\Services\v3\LoanService::class,
            \Shekel\ShekelLib\Services\v3\AuthService::class,
            \Shekel\ShekelLib\Services\v3\TransactionService::class,
        ];
        foreach ($services as $className) {
            $this->app->singleton($className, function (Application $app) use ($className) {
                /** @var ShekelBaseService $service */
                $service = new $className();
                $service->setToken($app[Request::class]->bearerToken() ?? '');
                return $service;
            });
        }

    }

    public function register() {
        $this->commands([
            \Shekel\ShekelLib\Commands\LogsPrune::class,
            \Shekel\ShekelLib\Commands\GenerateServiceSecret::class,
            \Shekel\ShekelLib\Commands\Tenant\Migration\TenantMigration::class,
            \Shekel\ShekelLib\Commands\Tenant\Migration\AdminMakeMigration::class,
            \Shekel\ShekelLib\Commands\Tenant\Migration\AdminMigrationCommand::class,
            \Shekel\ShekelLib\Commands\Tenant\Migration\RollbackAdminMigrationCommand::class,
            \Shekel\ShekelLib\Commands\Tenant\Migration\RollbackTenantMigration::class,
            \Shekel\ShekelLib\Commands\Tenant\Migration\TenantMakeMigration::class,
            \Shekel\ShekelLib\Commands\Tenant\Migration\TenantAwareMigration::class,
            \Shekel\ShekelLib\Commands\Tenant\Passport\PassportClientTenantCommand::class,
            \Shekel\ShekelLib\Commands\Tenant\Passport\PassportInstallTenantCommand::class,
        ]);

        (new \PragmaRX\Yaml\Package\Yaml)->loadToConfig(base_path('connection.yml'), 'database.connections');
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../resources/config/tenant-connection.php'),
            'database.connections'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../resources/config/shekel_redis.php'),
            'database.redis'
        );
        $this->mergeConfigFrom(
            realpath(__DIR__.'/../resources/config/tenant-redis.php'),
            'cache.stores'
        );

    }
}
