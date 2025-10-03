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
        
        // Get database size for accurate progress tracking
        $this->info('Calculating database size...');
        $totalSize = $this->getDatabaseSize($host, $port, $schemaName, $userName, $password);
        $totalSizeFormatted = $this->formatBytes($totalSize);
        $this->info("Database size: {$totalSizeFormatted}");
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
        
        // Create a progress bar with the actual database size as maximum
        $bar = $this->output->createProgressBar($totalSize);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->setMessage('Starting database dump...', 'status');
        $bar->start();
        
        while ($process->isRunning()) {
            sleep(1);
            clearstatcache(true, $outputFile);
            
            if (file_exists($outputFile)) {
                $currentSize = File::size($outputFile);
                $currentSizeFormatted = $this->formatBytes($currentSize);
                
                // Update progress bar to current file size
                $bar->setProgress($currentSize);
                $bar->setMessage("Dumping... ({$currentSizeFormatted} of {$totalSizeFormatted})", 'status');
            } else {
                $bar->setMessage('Initialising dump...', 'status');
            }
            
            $bar->display();
        }
        
        // Ensure we show 100% when complete
        if (file_exists($outputFile)) {
            $finalSize = File::size($outputFile);
            $bar->setProgress($finalSize);
        }
        $bar->setMessage('Dump completed!', 'status');
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

    /**
     * Format bytes into human readable format.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Get the total size of the database in bytes.
     */
    private function getDatabaseSize(string $host, int $port, string $database, string $username, string $password): int
    {
        try {
            $pdo = new \PDO(
                "mysql:host={$host};port={$port};dbname={$database}",
                $username,
                $password,
                [\PDO::ATTR_TIMEOUT => 10]
            );
            
            $stmt = $pdo->query("
                SELECT 
                    ROUND(SUM(data_length + index_length), 0) AS 'db_size'
                FROM information_schema.tables 
                WHERE table_schema = '{$database}'
            ");
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) $result['db_size'];
            
        } catch (\PDOException $e) {
            // If we can't get the size, return 0 and the progress bar will still work
            $this->warn('Could not determine database size: ' . $e->getMessage());
            return 0;
        }
    }
}
