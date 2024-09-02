<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Database\DatabaseManager;
use Symfony\Component\Console\Helper\ProgressBar;

class DatabaseOptimizeCommand extends DatabaseCommand
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

        if (empty($tables)) {
            $this->error('No tables found');
            return self::FAILURE;
        }

        $env = strtoupper(app()->environment());
        $message = sprintf('[%s] Starting Optimizing database tables: %s in %d seconds', $env, $database, implode(', ', $tables), DatabaseCommand::SECONDS_DELAY);
        $this->comment($message);
        self::wait();

        $bar = $this->output->createProgressBar(count($tables));
        $bar->setFormat(ProgressBar::FORMAT_VERBOSE);

        foreach ($tables as $table) {
            $result = $db->select("OPTIMIZE TABLE `{$table}`");
            if ($result[0]->Msg_text === 'OK') {
                $bar->advance();
            }
        }
        $bar->finish();
        $this->info(PHP_EOL . 'Done');

        return self::SUCCESS;
    }
}