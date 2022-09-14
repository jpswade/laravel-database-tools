<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Database\DatabaseManager;
use Jpswade\LaravelDatabaseTools\Services\DatabaseCharSetService;

/**
 * Updates the database tables to the charset and collation that you have configured.
 * @see https://gist.github.com/NBZ4live/04d5981eaf0244b57d0296b381e04195
 */
class DatabaseCharSetCommand extends DatabaseCommand
{
    const COMMAND = 'db:charset';

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = self::COMMAND;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command changes the charset';

    /**
     * Execute the console command.
     */
    public function handle(DatabaseManager $db): int
    {
        $connection = $db->connection();
        $schemaName = (string)$connection->getConfig('database');
        $charset = (string)$connection->getConfig('charset');
        $collation = (string)$connection->getConfig('collation');
        $service = new DatabaseCharSetService();
        $service($db, $schemaName, $charset, $collation, $this->output);
        $this->info('Done');
        return self::SUCCESS;
    }
}
