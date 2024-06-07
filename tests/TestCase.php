<?php

namespace Jpswade\LaravelDatabaseTools\Tests;

use Jpswade\LaravelDatabaseTools\Tests\Traits\SqliteProviderTrait;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use SqliteProviderTrait;
}
