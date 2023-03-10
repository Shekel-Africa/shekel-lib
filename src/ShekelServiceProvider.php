<?php

namespace Shekel\ShekelLib;

use Carbon\Laravel\ServiceProvider;

class ShekelServiceProvider extends ServiceProvider {

    public function boot() {
        $config = realpath(__DIR__.'/../resources/config/shekel.php');
        $this->publishes([
            $config => config_path('shekel.php')
        ], 'laravel-assets');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');


        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shekel-lib');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/shekel-lib'),
        ]);
    }

    public function register() {

    }
}
