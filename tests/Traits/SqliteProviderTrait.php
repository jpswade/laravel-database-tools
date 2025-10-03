<?php

namespace Jpswade\LaravelDatabaseTools\Tests\Traits;

use Jpswade\LaravelDatabaseTools\ServiceProvider;
use Jpswade\LaravelDatabaseTools\SqliteMysqlCompatibilityProvider;

trait SqliteProviderTrait
{
    protected function registerServiceProviders(): void
    {
        $this->app->register(SqliteMysqlCompatibilityProvider::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerServiceProviders();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
            SqliteMysqlCompatibilityProvider::class,
        ];
    }
}
