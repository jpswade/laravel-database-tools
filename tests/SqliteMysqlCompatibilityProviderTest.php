<?php

declare(strict_types=1);

namespace Jpswade\LaravelDatabaseTools\Tests;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Jpswade\LaravelDatabaseTools\SqliteMysqlCompatibilityProvider;
use PHPUnit\Framework\Assert;

class SqliteMysqlCompatibilityProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
    }

    public function test_boot_does_not_fail_when_sqlite_database_file_is_missing(): void
    {
        $this->expectNotToPerformAssertions();

        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => __DIR__.'/missing-database.sqlite',
            'prefix' => '',
        ]);

        DB::connection('sqlite');

        $this->app->register(SqliteMysqlCompatibilityProvider::class);
    }

    public function test_boot_configures_in_memory_sqlite_connections(): void
    {
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::connection('sqlite');

        $this->app->register(SqliteMysqlCompatibilityProvider::class);

        Assert::assertSame('5.6', DB::selectOne('select version() as version')->version);
    }
}
