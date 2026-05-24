<?php

declare(strict_types=1);

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Jpswade\LaravelDatabaseTools\ServiceProvider;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemException;
use League\Flysystem\StorageAttributes;
use RuntimeException;

class DatabaseGetFromBackupCommand extends DatabaseCommand
{
    /** @var string The name and signature of the console command. */
    protected $signature = 'db:getFromBackup {file?}';

    /** @var string The console command description. */
    protected $description = 'Get database backup file from backup.';

    private FilesystemAdapter $storage;

    private ?string $backupPath = null;

    /** @var array<string, mixed> */
    private array $config;

    /**
     * Execute the console command.
     *
     * @throws BindingResolutionException
     * @throws FilesystemException
     * @throws InvalidArgumentException
     * @throws \TypeError
     */
    public function handle(Config $config): int
    {
        $this->config = $config::get(ServiceProvider::CONFIG_KEY);
        $this->backupPath = $this->getBackupPath();
        $this->storage = $this->getFileSystem();
        $file = $this->argument('file');
        if ($file === null || $file === '') {
            $file = $this->getLatestFile();
        }
        $importFile = $this->fetchDatabaseArchive($file);
        if ($importFile !== null && $importFile !== '') {
            $this->info($importFile);
        }

        return self::SUCCESS;
    }

    /**
     * Grabs a storage instance for the relevant bucket.
     *
     * We do this dynamically, rather than use a disk instance as defined in the filesystems.php
     * configuration file directly, so that we can switch between buckets easily.
     *
     * @throws BindingResolutionException
     * @throws InvalidArgumentException
     */
    private function getFileSystem(): FilesystemAdapter
    {
        $config = $this->config['filesystem'];
        if (($config['driver'] ?? '') === '') {
            throw new InvalidArgumentException('Does not have a configured driver.');
        }
        if (($config['bucket'] ?? '') === '') {
            throw new InvalidArgumentException('Does not have a configured bucket.');
        }
        $name = $config['driver'];
        $driverMethod = 'create'.ucfirst($name).'Driver';
        if (method_exists(FilesystemManager::class, $driverMethod) === false) {
            throw new InvalidArgumentException("Driver [{$name}] is not supported.");
        }
        $filesystem = app()->make('filesystem');

        return $filesystem->{$driverMethod}($config); // @phpstan-ignore method.dynamicName
    }

    /**
     * @throws FilesystemException
     */
    private function getLatestFile(): string
    {
        $backupPath = $this->backupPath;
        $list = iterator_to_array($this->storage->listContents($backupPath), false); // @phpstan-ignore staticMethod.dynamicCall
        if ($list === []) {
            throw new RuntimeException('No files available at '.$backupPath);
        }
        $files = collect($list)
            ->map(fn (StorageAttributes $file): ?array => $this->normaliseListItem($file))
            ->filter();
        $files = $files->sortBy('timestamp')->reject(fn (array $file): bool => in_array(
            strtolower($file['extension']),
            [self::ZIP_EXTENSION, self::SQL_EXTENSION],
            true
        ) === false);
        $file = $files->last();
        if ($file === null) {
            throw new RuntimeException('Unable to get latest file');
        }

        return $file['basename'];
    }

    /**
     * @return array{basename: string, extension: string, timestamp: int}|null
     */
    private function normaliseListItem(StorageAttributes $file): ?array
    {
        if ($file instanceof FileAttributes) {
            $path = $file->path();
            $basename = basename($path);
            $extension = pathinfo($basename, PATHINFO_EXTENSION);

            return [
                'basename' => $basename,
                'extension' => $extension,
                'timestamp' => $file->lastModified() ?? 0,
            ];
        }

        return null;
    }

    private function fetchDatabaseArchive(?string $filename = null): ?string
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
        if (($config['filesystem']['path'] ?? '') !== '') {
            return $config['filesystem']['path'];
        }
        $backupConfig = Config::get('backup');
        $backupPath = $backupConfig['backup']['name'];
        if (($backupPath ?? '') === '') {
            throw new InvalidArgumentException('Unable to get backup config, have you configured `spatie/laravel-backup`?');
        }

        return $backupPath;
    }

    /**
     * @return list<string>
     */
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
        $importFilename = pathinfo($filename, PATHINFO_FILENAME).'.'.self::SQL_EXTENSION;

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
