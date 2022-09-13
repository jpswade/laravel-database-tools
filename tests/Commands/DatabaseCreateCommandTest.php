<?php

namespace Jpswade\LaravelDatabaseTools\Tests\Commands;

use Illuminate\Support\Facades\Config;
use Jpswade\LaravelDatabaseTools\Tests\TestCase;
use Symfony\Component\Console\Command\Command;

class DatabaseCreateCommandTest extends TestCase
{
    public function testCommand()
    {
        Config::set('database.default', 'testing');
        $this->artisan('db:create')->assertSuccessful()->assertExitCode(Command::SUCCESS);
    }
}