<?php

class Unzip
{
    const ERRORS = [
        ZIPARCHIVE::ER_MULTIDISK => 'Multi-disk zip archives not supported.',
        ZIPARCHIVE::ER_RENAME => 'Renaming temporary file failed.',
        ZIPARCHIVE::ER_CLOSE => 'Closing zip archive failed',
        ZIPARCHIVE::ER_SEEK => 'Seek error',
        ZIPARCHIVE::ER_READ => 'Read error',
        ZIPARCHIVE::ER_WRITE => 'Write error',
        ZIPARCHIVE::ER_CRC => 'CRC error',
        ZIPARCHIVE::ER_ZIPCLOSED => 'Containing zip archive was closed',
        ZIPARCHIVE::ER_NOENT => 'No such file.',
        ZIPARCHIVE::ER_EXISTS => 'File already exists',
        ZIPARCHIVE::ER_OPEN => 'Can\'t open file',
        ZIPARCHIVE::ER_TMPOPEN => 'Failure to create temporary file.',
        ZIPARCHIVE::ER_ZLIB => 'Zlib error',
        ZIPARCHIVE::ER_MEMORY => 'Memory allocation failure',
        ZIPARCHIVE::ER_CHANGED => 'Entry has been changed',
        ZIPARCHIVE::ER_COMPNOTSUPP => 'Compression method not supported.',
        ZIPARCHIVE::ER_EOF => 'Premature EOF',
        ZIPARCHIVE::ER_INVAL => 'Invalid argument',
        ZIPARCHIVE::ER_NOZIP => 'Not a zip archive',
        ZIPARCHIVE::ER_INTERNAL => 'Internal error',
        ZIPARCHIVE::ER_INCONS => 'Zip archive inconsistent',
        ZIPARCHIVE::ER_REMOVE => 'Can\'t remove file',
        ZIPARCHIVE::ER_DELETED => 'Entry has been deleted',
    ];

    function __invoke(string $file, string $path = '.'): ?array
    {
        $errors = self::ERRORS;
        if (function_exists('zip_open') === false) {
            $message = "zip_open() function does not exist.";
            throw new ErrorException($message);
        }
        $path = realpath($path);
        $file = realpath($file);
        if (is_writable($path) === false) {
            $message = "'$path' is not writable.";
            throw new ErrorException($message);
        }
        if (is_readable($file) === false) {
            $message = "'$file' is not readable.";
            throw new ErrorException($message);
        }
        $open = zip_open($file);
        if (is_resource($open) === false) {
            $error = ($errors[$open] ?? 'unknown');
            $message = sprintf("Unable to open zip '%s', error was '%s'.", $file, $error);
            throw new ErrorException($message);
        }
        $files = [];
        while ($entry = zip_read($open)) {
            $files[] = zip_entry_name($entry);
            zip_entry_open($open, $entry);
            if (substr(zip_entry_name($entry), -1) == DIRECTORY_SEPARATOR) {
                $dir = $path . DIRECTORY_SEPARATOR . substr(zip_entry_name($entry), 0, -1);
                if (file_exists($dir) === false) {
                    mkdir($dir);
                }
            } else {
                $name = $path . DIRECTORY_SEPARATOR . zip_entry_name($entry);
                $fh = fopen($name, 'w');
                fwrite($fh, zip_entry_read($entry, zip_entry_filesize($entry)), zip_entry_filesize($entry));
                fclose($fh);
            }
            zip_entry_close($entry);
        }
        zip_close($open);
        return $files;
    }
}
