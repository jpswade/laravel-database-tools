# Laravel Database Tools

The "missing" database toolset for Laravel. A set of commonly used Database Tools for Laravel.

With this package you can:

- Create a database (if it does not exist)
- Dump from another database to file
- Get and unzip from a database backup created by the [Spatie Backup package](https://github.com/spatie/laravel-backup)
- Import from file
- Update the charset and collation
- Fixes the "no such function" error by giving SQLite MySQL compatibility by creating the missing function using PDO for
  SQLite using PHP functions.

## Install

Install the package into your Laravel application:

* `composer require --dev jpswade/laravel-database-tools`

Note: It's wise to only install these tools in development by default, as it's rare you should need them in a production
environment.

## Configure

Publish and customise your own `dbtools.php` file:

* `php artisan vendor:publish --provider="Jpswade\LaravelDatabaseTools\ServiceProvider" --tag="config"`

This allows you to set the source database and/or filesystem for the backup.

* `dbtools.database` - Define the source database for the `db:dump` command, similar to Laravel databases config.
* `dbtools.filesystem` - Define the source filesystem for the `db:getFromBackup` command, similar to Laravel filesystems
  config.
* `dbtools.filesystem.path` - Define the path for the `db:getFromBackup` command.
* `dbtools.import` - Here you can define the `method` (command or normal) for the `db:importFromFile` command.

Note:

* The `db:getFromBackup` command falls back to the `spatie/laravel-backup` package for configuration.

## Usage

The commands are:

* `db:create` - Creates the database schema.
* `db:dump` - Fetch a copy of the latest database from the configured server.
* `db:getFromBackup` - Download database backup file from backup.
* `db:importFromFile {file?}` - Import data from a sql file into a database.
* `db:charset` - Changes the charset and collation to whatever the database is set to use.

### SQLite MySQL Compatability Provider

For `testing` you can add the provider to your Test:

```php
    protected function registerServiceProviders(): void
    {
        $this->app->register(SqliteMysqlCompatibilityProvider::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerServiceProviders();
    }
```

In production, follow the usual [Registering Providers](https://laravel.com/docs/9.x/providers#registering-providers) instructions:

In `config/app.php`, find the `providers` array and add:

```php
'providers' => [
    // Other Service Providers
 
    Jpswade\LaravelDatabaseTools\SqliteMysqlCompatibilityProvider::class,
],
```

## Limitations

These are limitations you'll come across if you use certain commands:

* The `db:getFromBackup` command relies on the `league/flysystem-aws-s3-v3 "^1.0"` package, when you use
  the [Amazon S3 Driver](https://laravel.com/docs/5.1/filesystem#configuration) as per the Laravel docs.
* The `db:dump` command depends on `spatie/db-dumper`.
* The commands have only been tested to work with MySQL at the moment, but could be extended to others.

## Troubleshooting

### Class 'League\Flysystem\AwsS3v3\AwsS3Adapter' not found

```% composer require league/flysystem-aws-s3-v3:~1.0```

Note: Needed for `db:getFromBackup` command to use the S3 Driver.

### Class 'Spatie\DbDumper\Databases\MySql' not found

```% composer require spatie/db-dumper```

Note: Needed by the `db:dump` command.
