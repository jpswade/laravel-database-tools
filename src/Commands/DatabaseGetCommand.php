<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Jpswade\LaravelDatabaseTools\ServiceProvider;
use Jpswade\LaravelDatabaseTools\Unzip;

class DatabaseGetCommand extends Command
{
    /** @var string @see https://github.com/spatie/laravel-backup/blob/11eb9f82bc0bd25ec69f5c169dde07290d913ce8/src/Tasks/Backup/BackupJob.php#L270 */
    public const DB_DUMPS_DIRECTORY = 'db-dumps';

    /** @var string */
    public const SQL_FILE_PATTERN = '*.sql';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:get';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download database backup file from backup.';

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
        $file = $this->getLatestFile();
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
        return Storage::build($config);
    }

    private function getLatestFile(): string
    {
        $backupPath = $this->backupPath;
        $files = collect($this->storage->listContents($backupPath));
        $files->sortByDesc('timestamp')->reject(function ($file) {
            return pathinfo($file['basename'], FILEINFO_EXTENSION) !== 'zip';
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
        if (file_exists(storage_path($filename)) === true) {
            $this->warn("File '$filename' already exists.'");
        } else {
            $this->info("File '$filename' does not exist, downloading...'");
            $storage = $this->storage;
            $size = $storage->getSize($path);
            $this->info(sprintf("Getting '%s', %d bytes", $filename, $size));
            $content = $storage->get($path);
            $this->info(sprintf("Got '%s', %d in length", $filename, strlen($content)));
            $bytes = file_put_contents(storage_path($filename), $content);
            $this->info(sprintf("Put '%s', wrote %d bytes", $filename, $bytes));
        }
        $this->info("Unzipping '$filename'...");
        $this->unzip($filename);
        $filePath = self::SQL_FILE_PATTERN;
        $storageFilePath = storage_path(self::DB_DUMPS_DIRECTORY . DIRECTORY_SEPARATOR . $filePath);
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
    }
}
