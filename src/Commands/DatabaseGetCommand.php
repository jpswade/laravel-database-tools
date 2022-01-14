<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class DatabaseGetCommand extends Command
{
    /** @var string @see https://github.com/spatie/laravel-backup/blob/main/src/Tasks/Backup/BackupJob.php#L270 */
    public const DB_DUMPS_DIRECTORY = 'db-dumps';

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
    protected $description = 'Download database backup file from S3 bucket.';

    /** @var Filesystem $storage */
    private $storage;

    /**
     * Execute the console command.
     *
     * @return int
     * @throws \Exception
     */
    public function handle(): int
    {
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
        return Storage::disk(self::FILESYSTEM);
    }

    /**
     * @return mixed
     */
    private function getLatestFile()
    {
        $backupConfig = Config::get('backup');
        $backupPath = $backupConfig['backup']['name'];
        $files = collect($this->storage->listContents($backupPath));
        $files->sortByDesc('timestamp');
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
        $backupConfig = Config::get('backup');
        $backupPath = $backupConfig['backup']['name'];
        $path = $backupPath . '/' . $filename;
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
        // @todo change this to use ZipArchive to unzip
        exec(sprintf('unzip %s -d %s', storage_path($filename), storage_path()));
        $filePath = '*.sql';
        $storageFilePath = storage_path(self::DB_DUMPS_DIRECTORY . DIRECTORY_SEPARATOR . $filePath);
        $glob = glob($storageFilePath);
        return array_shift($glob);
    }
}
