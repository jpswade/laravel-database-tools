<?php

declare(strict_types=1);

namespace Jpswade\LaravelDatabaseTools;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use Throwable;

class SqliteMysqlCompatibilityProvider extends ServiceProvider
{
    use Traits\SqliteTrait;

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $databaseManager = app(DatabaseManager::class);
        $connections = $databaseManager->getConnections();
        foreach ($connections as $connection) {
            try {
                self::setUpSqlite($connection);
            } catch (Throwable) {
                // Best-effort during bootstrap (e.g. package:discover before database.sqlite exists).
            }
        }
    }
}
