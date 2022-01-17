# Laravel Database Tools

The "missing" database toolset for Laravel. A set of commonly used Database Tools for Laravel.

With this package you can:

- Create a database (if it does not exist)
- Fetch from another database to file
- Get and unzip from a database backup created by the [Spatie Backup package](https://github.com/spatie/laravel-backup)
- Import from file

## Install

Install the package into your Laravel application:

* `composer require --dev jpswade/laravel-database-tools`

Note: It's wise to only install these tools in development by default, as it's rare you should need them in a production
environment.

## Configure

Publish and customise your own `dbtools.php` file.

* `php artisan vendor:publish --provider="Jpswade\LaravelDatabaseTools\ServiceProvider" --tag="config"`

This allows you to set the source database and/or filesystem for the backup.

## Usage

The commands are:

* `db:create` - Creates the database schema.
* `db:dump` - Fetch a copy of the latest database from the configured server.
* `db:getFromBackup` - Download database backup file from backup.
* `db:importFromFile {file?}` - Import data from a sql file into a database.

## Limitations

These are limitations you'll come across if you use certain commands:

* The `db:getFromBackup` command relies on the `spatie/laravel-backup` package for configuration.
* The `db:getFromBackup` command relies on the `league/flysystem-aws-s3-v3 "^1.0"` package, when you use the [Amazon S3 Driver](https://laravel.com/docs/5.1/filesystem#configuration) as per the Laravel docs.
* The `db:dump` command depends on `spatie/dbdumper`.
* The commands have only been tested to work with MySQL at the moment, but could be extended to others.
