<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Jpswade\LaravelDatabaseTools\ServiceProvider;
use Jpswade\LaravelDatabaseTools\Unzip;

class DatabaseGetFromBackupCommand extends Command
{
    /** @var string @see https://github.com/spatie/laravel-backup/blob/11eb9f82bc0bd25ec69f5c169dde07290d913ce8/src/Tasks/Backup/BackupJob.php#L270 */
    public const DB_DUMPS_DIRECTORY = 'db-dumps';

    /** @var string */
    public const SQL_EXTENSION = '.sql';

    /** @var string */
    public const SQL_FILE_PATTERN = '*' . self::SQL_EXTENSION;

    /** @var string */
    public const ZIP_EXTENSION = 'zip';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:getFromBackup {file?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get database backup file from backup.';

    /** @var Filesystem $storage */
    private $storage;

    /**
     * @var string
     */
    private $backupPath;

    /**
     * Execute the console command.
     *
     * @return int
     * @throws \Exception
     */
    public function handle(): int
    {
        $this->backupPath = $this->getBackupPath();
        if (empty($this->backupPath)) {
            $this->error('Unable to get backup config, have you done `composer require spatie/laravel-backup`?');
            return 1;
        }
        $this->storage = $this->getFileSystem();
        $file = $this->argument('file');
        if (empty($file) === true) {
            $file = $this->getLatestFile();
        }
        $importFile = $this->fetchDatabaseArchive($file);
        $this->info($importFile);
        return 0;
    }

    /**
     * Grabs a storage instance for the relevant bucket.
     *
     * We do this dynamically, rather than use a disk instance as defined in the filesystems.php
     * configuration file directly, so that we can switch between buckets easily.
     */
    private function getFileSystem(): Filesystem
    {
        $config = config(ServiceProvider::CONFIG_KEY . '.filesystem');
        if (empty($config['driver'])) {
            throw new InvalidArgumentException("Does not have a configured driver.");
        }
        $name = $config['driver'];
        $driverMethod = 'create' . ucfirst($name) . 'Driver';
        if (method_exists(FilesystemManager::class, $driverMethod) === false) {
            throw new InvalidArgumentException("Driver [{$name}] is not supported.");
        }
        $filesystem = app()->make('filesystem');
        return $filesystem->{$driverMethod}($config);
    }

    private function getLatestFile(): string
    {
        $backupPath = $this->backupPath;
        $files = collect($this->storage->listContents($backupPath));
        $files->sortByDesc('timestamp')->reject(function ($file) {
            return in_array($this->getExtension($file['basename']), ['zip', 'sql']) === false;
        });
        $file = $files->first();
        return $file['basename'];
    }

    /**
     * @param string|null $filename
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \League\Flysystem\FileNotFoundException
     */
    private function fetchDatabaseArchive(string $filename = null): string
    {
        $backupPath = $this->backupPath;
        $path = $backupPath . DIRECTORY_SEPARATOR . $filename;
        $file = $this->getTargetFile($filename);
        if (file_exists($file) === true) {
            $this->warn("File '$file' already exists.'");
            return $file;
        }
        if (file_exists($path) === true) {
            $this->warn("File '$filename' already exists.'");
        } else {
            $this->info("File '$filename' does not exist, downloading...'");
            $storage = $this->storage;
            $size = $storage->getSize($path);
            $this->info(sprintf("Getting '%s', %d bytes", $filename, $size));
            $content = $storage->get($path);
            $this->info(sprintf("Got '%s', %d in length", $filename, strlen($content)));
            $bytes = file_put_contents($file, $content);
            $this->info(sprintf("Put '%s', wrote %d bytes", $filename, $bytes));
        }
        if ($this->getExtension($file) === self::ZIP_EXTENSION) {
            $this->info("Unzipping '$filename'...");
            $this->unzip($file);
        }
        $filePath = self::SQL_FILE_PATTERN;
        $storageFilePath = $this->getTargetFile(self::DB_DUMPS_DIRECTORY . DIRECTORY_SEPARATOR . $filePath);
        $glob = glob($storageFilePath);
        return array_shift($glob);
    }

    /**
     * @return string
     */
    private function getBackupPath(): string
    {
        $backupConfig = Config::get('backup');
        return $backupConfig['backup']['name'];
    }

    private function unzip(string $filename): void
    {
        $unzip = new Unzip();
        $unzip($filename, storage_path());
        $unzippedFileName = '-';
        $unzippedFile = $this->getTargetFile($unzippedFileName);
        if (file_exists($unzippedFile)) {
            $this->info("Renaming '$unzippedFileName' to '$filename'...");
            rename($unzippedFile, $filename);
        }
    }

    /**
     * @param string|null $filename
     * @return string
     */
    private function getTargetFile(?string $filename): string
    {
        $importFilename = pathinfo($filename, PATHINFO_FILENAME) . self::SQL_EXTENSION;
        return storage_path($importFilename);
    }

    /**
     * @param $basename
     * @return array|string|string[]
     */
    private function getExtension($basename)
    {
        return pathinfo($basename, FILEINFO_EXTENSION);
    }
}
