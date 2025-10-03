<?php

namespace Jpswade\LaravelDatabaseTools\Tests;

use Illuminate\Support\Facades\Config;
use Jpswade\LaravelDatabaseTools\Tests\Traits\SqliteProviderTrait;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use SqliteProviderTrait;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('database.default', 'testing');
        Config::set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
