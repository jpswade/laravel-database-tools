<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Jpswade\LaravelDatabaseTools\ServiceProvider;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Exceptions\DumpFailed;
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
     *
     * @return int
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
        $outputFile = storage_path($filename);
        $message = sprintf('[%s] Starting fetching from %s@%s:%d/%s to %s', $env, $userName, $host, $port, $schemaName, $outputFile);
        $this->info($message);
        $mysqlDumper = MySql::create()
            ->setHost($host)
            ->setPort($port)
            ->setDbName($schemaName)
            ->setUserName($userName)
            ->setPassword($password)
            ->setGtidPurged('OFF')
            ->skipLockTables()
            ->addExtraOption('--no-tablespaces');
        $tempFileHandle = tmpfile();
        $process = $this->dumpToFile($mysqlDumper, $outputFile, $tempFileHandle);
        $process->start();
        $bar = $this->output->createProgressBar();
        $bar->setFormat('verbose');
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
        return 0;
    }

    /**
     * Dump the contents of the database to the given file.
     *
     * @param MySql $mysqlDumper
     * @param string $dumpFile
     * @param resource $tempFileHandle
     * @return Process
     */
    protected function dumpToFile(MySql $mysqlDumper, string $dumpFile, $tempFileHandle): Process
    {
        fwrite($tempFileHandle, $mysqlDumper->getContentsOfCredentialsFile());
        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];
        $command = $mysqlDumper->getDumpCommand($dumpFile, $temporaryCredentialsFile);
        return Process::fromShellCommandline($command, null, null, null, self::TIMEOUT);
    }
}
