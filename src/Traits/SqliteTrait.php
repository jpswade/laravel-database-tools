<?php

namespace Jpswade\LaravelDatabaseTools\Traits;

use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Jpswade\LaravelDatabaseTools\Services\SqliteService;

trait SqliteTrait
{
    protected static function setUpSqlite(Connection $connection): void
    {
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