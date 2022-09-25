<?php

namespace Jpswade\LaravelDatabaseTools;

use Jpswade\LaravelDatabaseTools\Services\SqliteService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\ServiceProvider;

class SqliteMysqlCompatibilityProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $databaseManager = app(DatabaseManager::class);
        $this->setUpSqlite($databaseManager);
    }

    protected static function setUpSqlite(DatabaseManager $databaseManager): void
    {
        $connection = $databaseManager->connection();
        if ($connection instanceof SQLiteConnection === false) {
            return;
        }

        /** Fix: Cannot add a NOT NULL column with default value NULL */
        $connection->getSchemaBuilder()->enableForeignKeyConstraints();

        /** Fix: no such function */
        $pdo = $connection->getPdo();
        new SqliteService($pdo);
    }
}
