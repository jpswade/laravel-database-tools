<?php

declare(strict_types=1);

namespace Jpswade\LaravelDatabaseTools\Traits;

use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Jpswade\LaravelDatabaseTools\Services\SqliteService;

trait SqliteTrait
{
    /**
     * @throws \Throwable
     */
    protected static function setUpSqlite(Connection $connection): void
    {
        if ($connection instanceof SQLiteConnection === false) {
            return;
        }

        if (self::sqliteDatabaseFileIsMissing($connection)) {
            return;
        }

        /** Fix: Cannot add a NOT NULL column with default value NULL */
        $connection->getSchemaBuilder()->enableForeignKeyConstraints();

        /** Fix: no such function */
        $pdo = $connection->getPdo();
        new SqliteService($pdo);
    }

    protected static function sqliteDatabaseFileIsMissing(SQLiteConnection $connection): bool
    {
        $path = $connection->getConfig('database');

        if (! is_string($path)) {
            return false;
        }

        if ($path === ':memory:' ||
            str_contains($path, '?mode=memory') ||
            str_contains($path, '&mode=memory')
        ) {
            return false;
        }

        $resolvedPath = realpath($path);

        if ($resolvedPath === false) {
            $resolvedPath = realpath(base_path($path));
        }

        return $resolvedPath === false;
    }
}
