<?php

namespace Jpswade\LaravelDatabaseTools\Tests\Traits;

use Jpswade\LaravelDatabaseTools\ServiceProvider;
use Jpswade\LaravelDatabaseTools\SqliteMysqlCompatibilityProvider;
use Tests\CreatesApplication;

trait SqliteProviderTrait
{
    use CreatesApplication;

    protected function registerServiceProviders(): void
    {
        $this->app->register(SqliteMysqlCompatibilityProvider::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerServiceProviders();
    }

    protected function getPackageProviders(): array
    {
        return [
            ServiceProvider::class,
            SqliteMysqlCompatibilityProvider::class,
        ];
    }
}
