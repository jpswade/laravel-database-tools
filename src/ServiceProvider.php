<?php

namespace Jpswade\LaravelDatabaseTools;

use Illuminate\Support\ServiceProvider as BaseProvider;

class ServiceProvider extends BaseProvider
{
    const CONFIG_KEY = 'dbtools';

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', self::CONFIG_KEY);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {

            $this->publishes([
                __DIR__.'/../config/config.php' => config_path(self::CONFIG_KEY . '.php'),
            ], 'config');

        }
    }
}
