<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Jpswade\LaravelDatabaseTools\ServiceProvider;
use League\Flysystem\FileNotFoundException;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseImportFromFileCommand extends DatabaseCommand
{
    /** @var int */
    public const SECONDS_DELAY = 10;

    private const MAX_ALLOWED_PACKET = 1000000000;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:importFromFile {file?} {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import data from a sql file into a database.';

    /**
     * Execute the console command.
     *
     * @return int
     * @throws FileNotFoundException
     */
    public function handle(): int
    {
        $force = $this->option('force');
        if ($force === false && app()->environment() === 'production') {
            throw new RuntimeException('Cannot be run in a production environment.');
        }
        $connection = $this->checkConnection();
        $this->checkMaxAllowedPacket();
        $this->setForeignKeyCheckOff();
        $importFile = $this->argument('file');
        $importFile = $this->getImportFile($importFile);
        $env = strtoupper(app()->environment());
        $message = sprintf('[%s] Starting import from %s to %s@%s:%s/%s in %d seconds', $env, $importFile, $connection['username'], $connection['host'], $connection['port'], $connection['database'], self::SECONDS_DELAY);
        $this->comment($message);
        self::wait();
        if (config(ServiceProvider::CONFIG_KEY . '.import.method') === 'command') {
            $this->databaseImportUsingCommandLine($importFile, $connection);
        } else {
            $this->databaseImport($importFile);
        }
        return 0;
    }

    /**
     * @return array
     */
    private function checkConnection(): array
    {
        $connectionName = config('database.default');
        $connection = config('database.connections')[$connectionName];
        if (!$connection['database']) {
            throw new InvalidArgumentException('Missing Database Name');
        }
        return $connection;
    }

    private function checkMaxAllowedPacket(): void
    {
        $query = "SHOW VARIABLES LIKE 'max_allowed_packet'";
        $result = DB::select($query);
        $value = (int)$result[0]->Value;
        if ($value < self::MAX_ALLOWED_PACKET) {
            $this->warn(sprintf('Max allowed packet is %d, lower than expected increasing to %d', $value, self::MAX_ALLOWED_PACKET));
            $query = 'SET GLOBAL max_allowed_packet=' . self::MAX_ALLOWED_PACKET;
            $result = DB::unprepared($query);
            if ($result) {
                $this->comment('Max allowed packet was increased to ' . self::MAX_ALLOWED_PACKET);
            } else {
                throw new RuntimeException('Unable to increase max allowed packet.');
            }
        }
    }

    private function setForeignKeyCheckOff(): void
    {
        $query = "SHOW VARIABLES LIKE 'FOREIGN_KEY_CHECKS'";
        $result = DB::select($query);
        if ($result[0]->Value === 'ON') {
            $this->comment('Foreign Key Check is set ON, setting to OFF.');
            $result = Schema::disableForeignKeyConstraints();
            if ($result) {
                $this->comment('Foreign Key Check is now set to OFF.');
            } else {
                throw new RuntimeException('Failed to set to OFF.');
            }
        } else {
            $this->comment('Foreign Key Check is already set OFF.');
        }
    }

    /**
     * @throws FileNotFoundException
     */
    private function getImportFile(?string $importFile = null): string
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

    private function getLatestSqlFile(): ?string
    {
        $files = $this->getSqlFiles();
        $files = array_combine(array_map('filemtime', $files), $files);
        krsort($files);
        return array_shift($files);
    }

    private static function wait(int $seconds = self::SECONDS_DELAY): void
    {
        for ($i = 1; $i <= $seconds; $i++) {
            printf('.');
            sleep(1);
        }
    }

    private function databaseImport(string $importFile): void
    {
        DB::disableQueryLog();
        $max = File::size($importFile);
        $bar = $this->output->createProgressBar($max);
        $bar->setFormat('verbose');
        $bar->start();
        $handle = fopen($importFile, 'rb');
        $length = memory_get_peak_usage(true);
        while (!feof($handle)) {
            $buffer = stream_get_line($handle, $length, ';' . PHP_EOL);
            $bar->advance(strlen($buffer));
            if (empty(trim($buffer)) === false) {
                DB::unprepared($buffer);
            }
        }
        $bar->finish();
    }

    private function databaseImportUsingCommandLine(string $importFile, array $connection): void
    {
        DB::disableQueryLog();
        $tempFileHandle = tmpfile();
        $bar = $this->output->createProgressBar();
        $bar->setFormat('verbose');
        $process = $this->importFromFile($importFile, $tempFileHandle, $connection);
        $process->start();
        while ($process->isRunning()) {
            sleep(1);
            $bar->advance();
        }
        $bar->finish();
        if (!$process->isSuccessful()) {
            throw new RuntimeException("The import process failed with exitcode {$process->getExitCode()} : {$process->getExitCodeText()} : {$process->getErrorOutput()}");
        }
    }

    /**
     * Import the contents of the database to the database.
     *
     * @param string $importFile
     * @param resource $tempFileHandle
     * @param array $connection
     * @return Process
     */
    protected function importFromFile(string $importFile, $tempFileHandle, array $connection): Process
    {
        $contents = [
            '[client]',
            "user = '{$connection['username']}'",
            "password = '{$connection['password']}'",
            "port = '{$connection['port']}'",
            "host = '{$connection['host']}'",
        ];

        $contents = implode(PHP_EOL, $contents);
        fwrite($tempFileHandle, $contents);
        $temporaryCredentialsFile = stream_get_meta_data($tempFileHandle)['uri'];

        $command = [
            'mysql',
            "--defaults-extra-file=\"{$temporaryCredentialsFile}\"",
        ];
        $command[] = $connection['database'];
        $query = sprintf('SET autocommit=0; source %s; COMMIT;', $importFile);
        $command[] = sprintf('-e "%s"', $query);
        $command = implode(' ', $command);
        return Process::fromShellCommandline($command);
    }
}
