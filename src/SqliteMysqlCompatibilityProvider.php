<?php

declare(strict_types=1);

namespace Jpswade\LaravelDatabaseTools;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;

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
            self::setUpSqlite($connection);
        }
    }
}
