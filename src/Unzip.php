<?php

namespace Jpswade\LaravelDatabaseTools;

use BadFunctionCallException;
use OutOfRangeException;
use RuntimeException;
use ZipArchive;

class Unzip
{
    public const ERRORS = [
        ZipArchive::ER_MULTIDISK => 'Multi-disk zip archives not supported.',
        ZipArchive::ER_RENAME => 'Renaming temporary file failed.',
        ZipArchive::ER_CLOSE => 'Closing zip archive failed',
        ZipArchive::ER_SEEK => 'Seek error',
        ZipArchive::ER_READ => 'Read error',
        ZipArchive::ER_WRITE => 'Write error',
        ZipArchive::ER_CRC => 'CRC error',
        ZipArchive::ER_ZIPCLOSED => 'Containing zip archive was closed',
        ZipArchive::ER_NOENT => 'No such file.',
        ZipArchive::ER_EXISTS => 'File already exists',
        ZipArchive::ER_OPEN => 'Can\'t open file',
        ZipArchive::ER_TMPOPEN => 'Failure to create temporary file.',
        ZipArchive::ER_ZLIB => 'Zlib error',
        ZipArchive::ER_MEMORY => 'Memory allocation failure',
        ZipArchive::ER_CHANGED => 'Entry has been changed',
        ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported.',
        ZipArchive::ER_EOF => 'Premature EOF',
        ZipArchive::ER_INVAL => 'Invalid argument',
        ZipArchive::ER_NOZIP => 'Not a zip archive',
        ZipArchive::ER_INTERNAL => 'Internal error',
        ZipArchive::ER_INCONS => 'Zip archive inconsistent',
        ZipArchive::ER_REMOVE => 'Can\'t remove file',
        ZipArchive::ER_DELETED => 'Entry has been deleted',
    ];

    public function __invoke(string $file, string $path = '.'): ?array
    {
        $errors = self::ERRORS;
        if (function_exists('zip_open') === false) {
            $message = "zip_open() function does not exist.";
            throw new BadFunctionCallException($message);
        }
        $path = realpath($path);
        $file = realpath($file);
        if (is_writable($path) === false) {
            $message = "'$path' is not writable.";
            throw new OutOfRangeException($message);
        }
        if (is_readable($file) === false) {
            $message = "'$file' is not readable.";
            throw new OutOfRangeException($message);
        }
        $open = zip_open($file);
        if (is_resource($open) === false) {
            $error = ($errors[$open] ?? 'unknown');
            $message = sprintf("Unable to open zip '%s', error was '%s'.", $file, $error);
            throw new OutOfRangeException($message);
        }
        $files = [];
        while ($entry = zip_read($open)) {
            $files[] = zip_entry_name($entry);
            zip_entry_open($open, $entry);
            if (substr(zip_entry_name($entry), -1) === DIRECTORY_SEPARATOR) {
                $dir = $path . DIRECTORY_SEPARATOR . substr(zip_entry_name($entry), 0, -1);
                if ((file_exists($dir) === false) && !mkdir($dir) && !is_dir($dir)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
                }
            } else {
                $name = $path . DIRECTORY_SEPARATOR . zip_entry_name($entry);
                $fh = fopen($name, 'wb');
                fwrite($fh, zip_entry_read($entry, zip_entry_filesize($entry)), zip_entry_filesize($entry));
                fclose($fh);
            }
            zip_entry_close($entry);
        }
        zip_close($open);
        return $files;
    }
}
