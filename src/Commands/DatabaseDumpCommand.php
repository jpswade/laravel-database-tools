<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Console\Command;
use Jpswade\LaravelDatabaseTools\ServiceProvider;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\Exceptions\DumpFailed;
use Symfony\Component\Process\Process;

class DatabaseDumpCommand extends Command
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
        $fields = ['host', 'database', 'username', 'password'];
        foreach ($fields as $field) {
            if (empty($config[$field])) {
                $this->error('Missing ' . $field);
            }
        }
        $host = $config['host'];
        $schemaName = $config['database'];
        $userName = $config['username'];
        $password = $config['password'];
        $env = strtoupper(config('app.env'));
        $nowTime = now()->format('YmdHis');
        $filename = sprintf('%s-%s.sql', $schemaName, $nowTime);
        $outputFile = storage_path($filename);
        $message = sprintf('[%s] Starting fetching from %s@%s/%s to %s', $env, $userName, $host, $schemaName, $outputFile);
        $this->info($message);
        $mysqlDumper = MySql::create()
            ->setHost($host)
            ->setDbName($schemaName)
            ->setUserName($userName)
            ->setPassword($password)
            ->setGtidPurged('OFF');
        $tempFileHandle = tmpfile();
        $process = $this->dumpToFile($mysqlDumper, $outputFile, $tempFileHandle);
        $process->start();
        $this->output->progressStart();
        while ($process->isRunning()) {
            sleep(1);
            clearstatcache(true, $outputFile);
            $size = filesize($outputFile);
            $this->output->progressAdvance($size);
        }
        $this->output->progressFinish();
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
     * @param $tempFileHandle
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
