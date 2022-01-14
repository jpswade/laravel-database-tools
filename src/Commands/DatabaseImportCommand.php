<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use League\Flysystem\FileNotFoundException;

class DatabaseImportCommand extends Command
{
    /** @var int */
    public const SECONDS_DELAY = 10;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:import {file?} {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import data into a database.';

    /**
     * @return string
     */
    private static function getLatestSqlFile()
    {
        $filePath = '*.sql';
        $storageFilePath = storage_path($filePath);
        $files = glob($storageFilePath);
        $files = array_combine(array_map('filemtime', $files), $files);
        krsort($files);
        return array_shift($files);
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws \Exception
     */
    public function handle()
    {
        $force = $this->option('force');
        if ($force === false && app()->environment() === 'production') {
            throw new \RuntimeException('Cannot be run in a production environment.');
        }
        $connectionName = config('database.default');
        $connection = config('database.connections')[$connectionName];
        $schemaName = $connection['database'];
        if (!$schemaName) {
            throw new InvalidArgumentException('Missing Database Name');
        }
        $importFile = $this->argument('file');
        $importFile = $this->getImportFile($importFile);
        $env = strtoupper(app()->environment());
        $warning = sprintf('[%s] Starting import from %s to %s@%s/%s in %d seconds', $env, $importFile, $connection['username'], $connection['host'], $schemaName, self::SECONDS_DELAY);
        $this->warn($warning);
        for ($i = 1; $i <= self::SECONDS_DELAY; $i++) {
            printf('.');
            sleep(1);
        }
        $this->databaseImport($importFile);
        return 0;
    }

    private function databaseImport(string $importFile): void
    {
        $length = memory_get_peak_usage(true);
        $handle = fopen($importFile, 'rb');
        $max = filesize($importFile);
        $bar = $this->output->createProgressBar($max);
        $bar->setFormat('verbose');
        $bar->start();
        while (!feof($handle)) {
            $buffer = stream_get_line($handle, $length, ";\n");
            $bar->advance(strlen($buffer));
            if (empty(trim($buffer)) === false) {
                DB::unprepared($buffer);
            }
        }
        $bar->finish();
    }

    /**
     * @throws FileNotFoundException
     */
    private function getImportFile(string $importFile): string
    {
        $importFile = empty($importFile) ? $this->getLatestSqlFile() : $importFile;
        if (empty($importFile)) {
            throw new FileNotFoundException('Missing SQL file');
        }
        if (is_file($importFile) === false) {
            if (is_file(storage_path($importFile))) {
                $importFile = storage_path($importFile);
            } else {
                throw new FileNotFoundException('File not found: ' . $importFile);
            }
        }
        return $importFile;
    }
}
