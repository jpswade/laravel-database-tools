<?php

namespace Jpswade\LaravelDatabaseTools;

use Illuminate\Support\ServiceProvider as BaseProvider;
use Jpswade\LaravelDatabaseTools\Commands\DatabaseCreateCommand;
use Jpswade\LaravelDatabaseTools\Commands\DatabaseFetchCommand;
use Jpswade\LaravelDatabaseTools\Commands\DatabaseGetCommand;
use Jpswade\LaravelDatabaseTools\Commands\DatabaseImportFromFileCommand;

class ServiceProvider extends BaseProvider
{
    public const CONFIG_KEY = 'dbtools';

    public const COMMANDS = [
        DatabaseCreateCommand::class,
        DatabaseFetchCommand::class,
        DatabaseGetCommand::class,
        DatabaseImportFromFileCommand::class,
    ];

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', self::CONFIG_KEY);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->getPublishes();
            $this->getCommands();
        }
    }

    /**
     * @return void
     */
    private function getPublishes(): void
    {
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path(self::CONFIG_KEY . '.php'),
        ], 'config');
    }

    /**
     * @return void
     */
    private function getCommands(): void
    {
        $this->commands(self::COMMANDS);
    }
}
