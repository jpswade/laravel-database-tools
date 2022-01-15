# Laravel Database Tools

The "missing" database toolset for Laravel. A set of commonly used Database Tools for Laravel.
 
With this package you can:
- Create a database (if it does not exist)
- Fetch from another database to file
- Get and unzip from a database backup created by the [Spatie Backup package](https://github.com/spatie/laravel-backup)
- Import from file

## Install

Install the package into your Laravel application:

* `composer require jpswade/laravel-database-tools`

## Configure

Publish and customise your own `dbtools.php` file.

* `php artisan vendor:publish --provider="Jpswade\LaravelDatabaseTools\ServiceProvider" --tag="config"`

This allows you to set the source database and/or filesystem for the backup.
