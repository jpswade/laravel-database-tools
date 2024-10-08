<?php

namespace Jpswade\LaravelDatabaseTools\Commands;

use Illuminate\Console\Command;

class DatabaseCommand extends Command
{

    /** @var int Delay in seconds before starting the import */
    public const SECONDS_DELAY = 10;

    /**
     * @var string
     * @see https://github.com/spatie/laravel-backup/blob/11eb9f82bc0bd25ec69f5c169dde07290d913ce8/src/Tasks/Backup/BackupJob.php#L270
     */
    public const DB_DUMPS_DIRECTORY = 'db-dumps';

    /** @var string */
    public const SQL_EXTENSION = 'sql';

    /** @var string */
    public const SQL_FILE_PATTERN = '*.' . self::SQL_EXTENSION;

    /** @var string */
    public const ZIP_EXTENSION = 'zip';

    protected function getSqlFiles()
    {
        $filePath = self::SQL_FILE_PATTERN;
        $storageFilePath = $this->getDumpPath($filePath);
        return glob($storageFilePath);
    }

    protected function getDumpPath(string $filePath = ''): string
    {
        $path = $this->storagePath(self::DB_DUMPS_DIRECTORY);
        return $path . DIRECTORY_SEPARATOR . $filePath;
    }

    protected function storagePath(string $path = ''): string
    {
        return storage_path($path);
    }

    protected static function wait(int $seconds = self::SECONDS_DELAY): void
    {
        for ($i = 1; $i <= $seconds; $i++) {
            printf('.');
            sleep(1);
        }
    }
}
