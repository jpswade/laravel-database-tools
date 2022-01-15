<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates the database schema.
 * @see https://github.com/laravel/framework/issues/19412
 */
class DatabaseCreateCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'db:create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command creates a new database';

    /**
     * Execute the console command.
     * @return int
     */
    public function handle(): int
    {
        $connectionName = config('database.default');
        $connection = config('database.connections')[$connectionName];
        $schemaName = $connection['database'];
        if (empty($schemaName)) {
            throw new InvalidArgumentException('Missing Database Name');
        }
        $query = sprintf(
            'CREATE DATABASE IF NOT EXISTS %s CHARACTER SET %s COLLATE %s;',
            $schemaName,
            $connection['charset'],
            $connection['collation']
        );
        config(["database.connections.{$connectionName}.database" => null]);
        DB::reconnect('mysql');
        DB::statement($query);
        config(["database.connections.{$connectionName}.database" => $schemaName]);
        $this->info('Done');
        return 0;
    }
}
