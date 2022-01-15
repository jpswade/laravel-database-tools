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

    /** @var string */
    public const CONFIG_PATH = __DIR__ . '/config/config.php';

    public function register()
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, self::CONFIG_KEY);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->getPublishes();
            $this->getCommands();
        }
    }

    private function getPublishes(): void
    {
        $this->publishes([
            self::CONFIG_PATH => config_path(self::CONFIG_KEY . '.php'),
        ], 'config');
    }

    private function getCommands(): void
    {
        $this->commands(self::COMMANDS);
    }
}
