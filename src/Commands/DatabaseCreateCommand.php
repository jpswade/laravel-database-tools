<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager as DB;
use InvalidArgumentException;

/**
 * Creates the database schema.
 * @see https://github.com/laravel/framework/issues/19412
 */
class DatabaseCreateCommand extends DatabaseCommand
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
     */
    public function handle(DB $db, Repository $config): int
    {
        $this->info('Creating database if it does not exist...');
        $connectionName = $config->get('database.default');
        $connection = $config->get('database.connections')[$connectionName];
        $schemaName = $connection['database'];
        if (empty($schemaName)) {
            throw new InvalidArgumentException('Missing Database Name');
        }
        $this->info(sprintf('Creating database "%s" via "%s" connection (if it does not exist)...', $schemaName, $connectionName));
        $config->set(["database.connections.{$connectionName}.database" => null]);
        $db->reconnect($connectionName);
        $builder = $db->getSchemaBuilder();
        $builder->createDatabase($schemaName);
        $config->set(["database.connections.{$connectionName}.database" => $schemaName]);
        $this->info('Done');
        return self::SUCCESS;
    }
}
