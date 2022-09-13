<?php

namespace Jpswade\LaravelDatabaseTools\Tests;

use Jpswade\LaravelDatabaseTools\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
