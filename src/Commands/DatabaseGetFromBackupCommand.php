<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Jpswade\LaravelDatabaseTools\ServiceProvider;
use RuntimeException;

class DatabaseGetFromBackupCommand extends DatabaseCommand
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'db:getFromBackup {file?}';

    /** @var string The console command description. */
    protected $description = 'Get database backup file from backup.';

    /** @var FilesystemAdapter $storage */
    private $storage;

    /** @var null|string */
    private $backupPath = null;

    /** @var array */
    private $config;

    /**
     * Execute the console command.
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
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
        return self::SUCCESS;
    }

    /**
     * Grabs a storage instance for the relevant bucket.
     *
     * We do this dynamically, rather than use a disk instance as defined in the filesystems.php
     * configuration file directly, so that we can switch between buckets easily.
     */
    private function getFileSystem(): FilesystemAdapter
    {
        $config = $this->config['filesystem'];
        if (empty($config['driver'])) {
            throw new InvalidArgumentException('Does not have a configured driver.');
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
            throw new RuntimeException('No files available at '. $backupPath);
        }
        $files = collect($list);
        $files = $files->sortBy('timestamp')->reject(function (array $file) {
            return in_array(strtolower($file['extension']), [self::ZIP_EXTENSION, self::SQL_EXTENSION], true) === false;
        });
        $file = $files->last();
        if ($file === null) {
            throw new RuntimeException('Unable to get latest file');
        }
        return $file['basename'];
    }

    /**
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
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
            $size = $storage->size($path);
            if ($size === 0) {
                $this->error(sprintf('Unable to continue, file %s is empty.', $path));
                return null;
            }
            $this->info(sprintf("Getting '%s', %d bytes", $filename, $size));
            $filePath = $this->storagePath($filename);
            $this->info(sprintf("Reading stream from '%s'", $filename));
            $stream = $storage->readStream($path);
            $this->info(sprintf("Saving to '%s'", $filePath));
            $bytes = file_put_contents($filePath, stream_get_contents($stream), FILE_APPEND);
            $this->info(sprintf("Put '%s', wrote %d bytes", $filename, $bytes));
        }
        if ($this->isZipFile($filename)) {
            $this->info("Unzipping '$filename'...");
            $filePath = $this->storagePath($filename);
            $files = $this->unzip($filePath);
            $file = array_shift($files);
        }
        return $file;
    }

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

    private function getTargetFile(?string $filename): string
    {
        $importFilename = pathinfo($filename, PATHINFO_FILENAME) . '.' . self::SQL_EXTENSION;
        return $this->storagePath($importFilename);
    }

    private function getExtension(string $basename): string
    {
        $array = explode('.', $basename);
        return array_pop($array);
    }

    private function execUnzip(string $filename): void
    {
        $sourceFile = $filename[0] === DIRECTORY_SEPARATOR ? $filename : $this->storagePath($filename);
        exec(sprintf('unzip %s -d %s', $sourceFile, $this->storagePath()));
    }

    private function isZipFile(string $file): bool
    {
        return $this->getExtension($file) === self::ZIP_EXTENSION;
    }
}
