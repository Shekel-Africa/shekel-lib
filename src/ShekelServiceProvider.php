<?php

namespace Shekel\ShekelLib;

use Carbon\Laravel\ServiceProvider;

class ShekelServiceProvider extends ServiceProvider {

    public function boot() {
        $config = realpath(__DIR__.'/../resources/config/shekel.php');
        $this->publishes([
            $config => config_path('shekel.php')
        ], 'laravel-assets');
    }

    public function register() {
        
    }
}