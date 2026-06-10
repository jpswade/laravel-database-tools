<?php

declare(strict_types=1);

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Jpswade\LaravelDatabaseTools\ServiceProvider;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Process\Process;
use ValueError;

class DatabaseImportFromFileCommand extends DatabaseCommand
{
    /** @var int Delay in seconds before starting the import */
    public const SECONDS_DELAY = 10;

    /** @var int Maximum Allowed Packet to check for */
    private const MAX_ALLOWED_PACKET = 1000000000;

    /** @var string The name and signature of the console command. */
    protected $signature = 'db:importFromFile {file?} {--force : Force the operation to run when in production}';

    /** @var string The console command description. */
    protected $description = 'Import data from a sql file into a database.';

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws ValueError
     */
    public function handle(): int
    {
        $force = $this->option('force');
        if ($force === false && app()->environment() === 'production') {
            throw new RuntimeException('Cannot be run in a production environment');
        }
        $connection = $this->checkConnection();
        $this->checkMaxAllowedPacket();
        $this->setForeignKeyCheckOff();
        $importFile = $this->argument('file');
        try {
            $importFile = $this->getImportFile($importFile);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $env = strtoupper(app()->environment());
        $message = sprintf('[%s] Starting import from %s to %s@%s:%s/%s in %d seconds', $env, $importFile, $connection['username'], $connection['host'], $connection['port'], $connection['database'], self::SECONDS_DELAY);
        $this->comment($message);
        self::wait();
        if (config(ServiceProvider::CONFIG_KEY.'.import.method') === 'command') {
            $this->databaseImportUsingCommandLine($importFile, $connection);
        } else {
            $this->databaseImport($importFile);
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkConnection(): array
    {
        $connectionName = config('database.default');
        $connection = config('database.connections')[$connectionName];
        if (($connection['database'] ?? '') === '') {
            throw new InvalidArgumentException('Missing Database Name');
        }

        return $connection;
    }

    private function checkMaxAllowedPacket(): void
    {
        if (config(ServiceProvider::CONFIG_KEY.'.import.increase_max_allowed_packet', true) === false) {
            return;
        }

        $query = "SHOW VARIABLES LIKE 'max_allowed_packet'";
        $result = DB::select($query);
        $value = (int) $result[0]->Value;
        if ($value < self::MAX_ALLOWED_PACKET) {
            $this->warn(sprintf('Max allowed packet is %d, lower than expected increasing to %d', $value, self::MAX_ALLOWED_PACKET));
            $query = 'SET GLOBAL max_allowed_packet='.self::MAX_ALLOWED_PACKET;
            $result = DB::unprepared($query);
            if ($result) {
                $this->comment('Max allowed packet was increased to '.self::MAX_ALLOWED_PACKET);
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
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws ValueError
     */
    private function getImportFile(?string $importFile = null): string
    {
        if ($importFile === null || $importFile === '') {
            $importFile = $this->getLatestSqlFile();
        }
        if ($importFile === null || $importFile === '') {
            throw new InvalidArgumentException('Missing SQL file');
        }
        if (is_file($importFile) === false) {
            if (is_file(storage_path($importFile))) {
                $importFile = storage_path($importFile);
            } else {
                throw new RuntimeException(sprintf('SQL file not found: %s', $importFile));
            }
        }

        return $importFile;
    }

    /**
     * @throws ValueError
     */
    private function getLatestSqlFile(): ?string
    {
        $files = $this->getSqlFiles();
        $files = array_combine(array_map('filemtime', $files), $files);
        krsort($files);

        return array_shift($files);
    }

    private function databaseImport(string $importFile): void
    {
        DB::disableQueryLog();
        $max = File::size($importFile);
        $bar = $this->output->createProgressBar($max);
        $bar->setFormat(ProgressBar::FORMAT_VERBOSE);
        $bar->start();
        $handle = fopen($importFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Unable to open import file: {$importFile}");
        }
        $length = memory_get_peak_usage(true);
        while (feof($handle) === false) {
            $buffer = stream_get_line($handle, $length, ';'.PHP_EOL);
            if ($buffer === false) {
                break;
            }
            $bar->advance(strlen($buffer));
            if (trim($buffer) !== '') {
                DB::unprepared($buffer);
            }
        }
        fclose($handle);
        $bar->finish();
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    private function databaseImportUsingCommandLine(string $importFile, array $connection): void
    {
        DB::disableQueryLog();
        $tempFileHandle = tmpfile();
        $fileHandle = fopen($importFile, 'rb');
        if ($fileHandle === false) {
            throw new RuntimeException("Unable to open import file: {$importFile}");
        }
        $max = File::size($importFile);
        $bar = $this->output->createProgressBar($max);
        $bar->setFormat(ProgressBar::FORMAT_VERBOSE);
        $process = $this->importFromFile($fileHandle, $tempFileHandle, $connection);
        $process->start();
        $bar->start();
        while ($process->isRunning()) {
            sleep(1);
            $position = ftell($fileHandle);
            if ($position !== false) {
                $bar->setProgress($position);
            }
        }
        $position = ftell($fileHandle);
        if ($position !== false) {
            $bar->setProgress($position);
        }
        $bar->finish();
        fclose($fileHandle);
        if (! $process->isSuccessful()) {
            throw new RuntimeException("The import process failed with exitcode {$process->getExitCode()} : {$process->getExitCodeText()} : {$process->getErrorOutput()}");
        }
    }

    /**
     * Import the contents of the database to the database.
     *
     * @param  resource  $importFileHandle
     * @param  resource  $tempFileHandle
     * @param  array<string, mixed>  $connection
     */
    protected function importFromFile($importFileHandle, $tempFileHandle, array $connection): Process
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
        $command[] = '--binary-mode';
        // The dump file's own `SET FOREIGN_KEY_CHECKS=0` is not always honoured early
        // enough by MariaDB to allow a CREATE TABLE that references a not-yet-created
        // table (mysqldump emits tables alphabetically). Disabling at session start
        // via --init-command guarantees the import succeeds regardless of order.
        $command[] = '--init-command="SET SESSION FOREIGN_KEY_CHECKS=0; SET SESSION UNIQUE_CHECKS=0"';
        $command = implode(' ', $command);
        $process = Process::fromShellCommandline($command);
        $process->setInput($importFileHandle);

        return $process;
    }
}
