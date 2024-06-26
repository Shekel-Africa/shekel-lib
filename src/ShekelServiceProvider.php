<?php

namespace Shekel\ShekelLib;

use Carbon\Laravel\ServiceProvider;
use Opcodes\LogViewer\LogViewerServiceProvider;
use Spatie\Health\HealthServiceProvider;

class ShekelServiceProvider extends ServiceProvider {

    public function boot() {
        $config = realpath(__DIR__.'/../resources/config/shekel.php');
        $this->publishes([
            $config => config_path('shekel.php'),
        ], 'laravel-assets');
        $this->publishes(self::pathsToPublish(LogViewerServiceProvider::class), 'shekel-deps');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');


        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shekel-lib');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/shekel-lib'),
        ], 'laravel-assets');
        $this->commands([
            \Shekel\ShekelLib\Commands\LogsPrune::class,
            \Shekel\ShekelLib\Commands\GenerateServiceSecret::class
        ]);
    }

    public function register() {

    }
}
