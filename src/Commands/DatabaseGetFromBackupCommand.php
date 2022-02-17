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
    public const SQL_EXTENSION = 'sql';

    /** @var string */
    public const SQL_FILE_PATTERN = '*.' . self::SQL_EXTENSION;

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
     * @var null|string
     */
    private $backupPath = null;

    /**
     * @var array
     */
    private $config;

    /**
     * Execute the console command.
     *
     * @param Config $config
     * @return int
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function handle(Config $config): int
    {
        $this->config = $config::get(ServiceProvider::CONFIG_KEY);
        $this->backupPath = $this->getBackupPath();
        $this->storage = $this->getFileSystem();
        $file = $this->argument('file');
        if (empty($file) === true) {
            $file = $this->getLatestFile();
        }
        $importFile = $this->fetchDatabaseArchive($file);
        if ($importFile) {
            $this->info($importFile);
        }
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
        $config = $this->config['filesystem'];
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
        $list = $this->storage->listContents($backupPath);
        if (empty($list)) {
            throw new \RuntimeException('No files available');
        }
        $files = collect($list);
        $files->sortByDesc('timestamp')->reject(function ($file) {
            return in_array($this->getExtension($file['basename']), [self::ZIP_EXTENSION, self::SQL_EXTENSION], false) === false;
        });
        $file = $files->first();
        if ($file === null) {
            throw new \RuntimeException('Unable to get latest file');
        }
        return $file['basename'];
    }

    /**
     * @param string|null $filename
     * @return ?string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws \League\Flysystem\FileNotFoundException
     */
    private function fetchDatabaseArchive(string $filename = null): ?string
    {
        $backupPath = $this->backupPath;
        $path = implode(DIRECTORY_SEPARATOR, [$backupPath, $filename]);
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
            if ($size === 0) {
                $this->error(sprintf('Unable to continue, file %s is empty.', $path));
                return null;
            }
            $this->info(sprintf("Getting '%s', %d bytes", $filename, $size));
            $filePath = storage_path($filename);
            $this->info(sprintf("Reading stream from '%s'", $filename));
            $stream = $storage->readStream($path);
            $this->info(sprintf("Saving to '%s'", $filePath));
            $bytes = file_put_contents($filePath, stream_get_contents($stream), FILE_APPEND);
            $this->info(sprintf("Put '%s', wrote %d bytes", $filename, $bytes));
        }
        if ($this->isZipFile($filename)) {
            $this->info("Unzipping '$filename'...");
            $filePath = storage_path($filename);
            $files = $this->unzip($filePath);
            $file = array_shift($files);
        }
        return $file;
    }

    /**
     * @return string
     */
    private function getBackupPath(): string
    {
        $config = $this->config;
        if (empty($config['filesystem']['path']) === false) {
            return $config['filesystem']['path'];
        }
        $backupConfig = Config::get('backup');
        $backupPath = $backupConfig['backup']['name'];
        if (empty($backupPath)) {
            throw new InvalidArgumentException('Unable to get backup config, have you configured `spatie/laravel-backup`?');
        }
        return $backupPath;
    }

    private function unzip(string $filename): array
    {
        $this->execUnzip($filename);
        $unzippedFileName = '-';
        $unzippedFile = $this->getTargetFile($unzippedFileName);
        if (file_exists($unzippedFile)) {
            $this->info("Renaming '$unzippedFileName' to '$filename'...");
            rename($unzippedFile, $filename);
        }
        return $this->getSqlFiles();
    }

    /**
     * @param string|null $filename
     * @return string
     */
    private function getTargetFile(?string $filename): string
    {
        $importFilename = pathinfo($filename, PATHINFO_FILENAME) . '.' . self::SQL_EXTENSION;
        return storage_path($importFilename);
    }

    /**
     * @param $basename
     * @return array|string|string[]
     */
    private function getExtension($basename)
    {
        $array = explode('.', $basename);
        return array_pop($array);
    }

    /**
     * @return array|false
     */
    private function getSqlFiles()
    {
        $filePath = self::SQL_FILE_PATTERN;
        $storageFilePath = storage_path(self::DB_DUMPS_DIRECTORY . DIRECTORY_SEPARATOR . $filePath);
        return glob($storageFilePath);
    }

    private function execUnzip(string $filename, $targetDirectory = null): void
    {
        $targetDirectory = $targetDirectory ?: storage_path();
        $sourceFile = $filename[0] === DIRECTORY_SEPARATOR ? $filename : storage_path($filename);
        exec(sprintf('unzip %s -d %s', $sourceFile, $targetDirectory));
    }

    /**
     * @param string $file
     * @return bool
     */
    private function isZipFile(string $file): bool
    {
        return $this->getExtension($file) === self::ZIP_EXTENSION;
    }
}
