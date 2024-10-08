<?php

namespace Jpswade\LaravelDatabaseTools;

use Illuminate\Support\ServiceProvider as BaseProvider;
use Jpswade\LaravelDatabaseTools\Commands\DatabaseCharSetCommand;
use Jpswade\LaravelDatabaseTools\Commands\DatabaseCreateCommand;
use Jpswade\LaravelDatabaseTools\Commands\DatabaseDumpCommand;
use Jpswade\LaravelDatabaseTools\Commands\DatabaseGetFromBackupCommand;
use Jpswade\LaravelDatabaseTools\Commands\DatabaseImportFromFileCommand;
use Jpswade\LaravelDatabaseTools\Commands\DatabaseOptimizeCommand;

class ServiceProvider extends BaseProvider
{
    public const CONFIG_KEY = 'dbtools';

    public const COMMANDS = [
        DatabaseCharSetCommand::class,
        DatabaseCreateCommand::class,
        DatabaseDumpCommand::class,
        DatabaseGetFromBackupCommand::class,
        DatabaseImportFromFileCommand::class,
        DatabaseOptimizeCommand::class,
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
