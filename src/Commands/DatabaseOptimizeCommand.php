<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Console\Command as BaseCommand;
use Illuminate\Database\DatabaseManager;

class DatabaseOptimizeCommand extends BaseCommand
{
    protected $signature = 'db:optimize {--database=default} {--table=*}';
    protected $description = 'Optimizes database tables';

    public function handle(DatabaseManager $db): int
    {
        $database = $this->option('database') === 'default' ? config('database.default') : $this->option('database');
        $tables = $this->option('table');

        if (empty($tables)) {
            $tables = $db->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$database}'");
            $tables = array_column($tables, 'TABLE_NAME');
        }

        $this->info('Starting...');
        $bar = $this->output->createProgressBar(count($tables));

        foreach ($tables as $table) {
            $result = $db->select("OPTIMIZE TABLE `{$table}`");
            if ($result[0]->Msg_text === 'OK') {
                $bar->advance();
            }
        }
        $bar->finish();
        $this->info('Done');

        return self::SUCCESS;
    }
}