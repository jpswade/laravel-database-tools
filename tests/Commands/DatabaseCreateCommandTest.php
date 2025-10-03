<?php

namespace Jpswade\LaravelDatabaseTools\Tests\Commands;

use Illuminate\Support\Facades\Config;
use Jpswade\LaravelDatabaseTools\Tests\TestCase;
use Symfony\Component\Console\Command\Command;

class DatabaseCreateCommandTest extends TestCase
{
    public function testCommandSucceedsWithValidConnection()
    {
        Config::set('database.default', 'testing');
        $this->artisan('db:create')
            ->expectsOutput('Done')
            ->assertExitCode(Command::SUCCESS);
    }

    public function testCommandFailsWithInvalidConnection()
    {
        Config::set('database.default', 'invalid_connection');
        $this->expectException(\InvalidArgumentException::class);
        $this->artisan('db:create')->run();
    }

    public function testCommandOutputsExpectedMessages()
    {
        Config::set('database.default', 'testing');
        $this->artisan('db:create')
            ->expectsOutput('Done')
            ->assertExitCode(Command::SUCCESS);
    }
}
