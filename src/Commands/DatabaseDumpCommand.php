<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Support\Facades\File;
use Jpswade\LaravelDatabaseTools\ServiceProvider;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Exceptions\DumpFailed;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Process\Process;

class DatabaseDumpCommand extends DatabaseCommand
{
    private const TIMEOUT = 0;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:dump';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch a copy of the latest database from the configured server.';

    /**
     * Execute the console command.
     * @throws DumpFailed
     */
    public function handle(): int
    {
        $config = config(ServiceProvider::CONFIG_KEY . '.database');
        $fields = ['host', 'port', 'database', 'username', 'password'];
        foreach ($fields as $field) {
            if (empty($config[$field])) {
                $this->error('Missing ' . $field);
            }
        }
        $host = $config['host'];
        $port = $config['port'];
        $schemaName = $config['database'];
        $userName = $config['username'];
        $password = $config['password'];
        $env = strtoupper(config('app.env'));
        $nowTime = now()->format('YmdHis');
        $filename = sprintf('%s-%s.sql', $schemaName, $nowTime);
        $outputFile = $this->getDumpPath($filename);
        $message = sprintf('[%s] Starting fetching from %s@%s:%d/%s to %s', $env, $userName, $host, $port, $schemaName, $outputFile);
        $this->info($message);
        $mysqlDumper = MySql::create()
            ->setHost($host)
            ->setPort($port)
            ->setDbName($schemaName)
            ->setUserName($userName)
            ->setPassword($password)
            ->skipLockTables()
            ->addExtraOption('--no-tablespaces');

        if (isset($config['is_maria'])
            && !$config['is_maria']) {
            $mysqlDumper->setGtidPurged('OFF');
        }
        $tempFileHandle = tmpfile();
        $this->checkFilePathExists(dirname($outputFile));
        $process = $this->dumpToFile($mysqlDumper, $outputFile, $tempFileHandle);
        $process->start();
        $bar = $this->output->createProgressBar();
        $bar->setFormat(ProgressBar::FORMAT_VERBOSE);
        while ($process->isRunning()) {
            sleep(1);
            clearstatcache(true, $outputFile);
            $size = File::size($outputFile);
            $bar->advance($size);
        }
        $bar->finish();
        if (!$process->isSuccessful()) {
            throw DumpFailed::processDidNotEndSuccessfully($process);
        }
        if (!file_exists($outputFile)) {
            throw DumpFailed::dumpfileWasNotCreated();
        }
        if (filesize($outputFile) === 0) {
            throw DumpFailed::dumpfileWasEmpty();
        }
        $message = 'Created MySql Dump outputFile: ' . $outputFile;
        $this->info($message);
        return self::SUCCESS;
    }

    /**
     * Dump the contents of the database to the given file.
     */
    protected function dumpToFile(MySql $mysqlDumper, string $dumpFile, $tempFileHandle): Process
    {
        fwrite($tempFileHandle, $mysqlDumper->getContentsOfCredentialsFile());
        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];
        $command = $mysqlDumper->getDumpCommand($dumpFile, $temporaryCredentialsFile);
        return Process::fromShellCommandline($command, null, null, null, self::TIMEOUT);
    }

    private function checkFilePathExists(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
