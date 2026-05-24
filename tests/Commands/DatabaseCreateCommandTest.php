<?php

declare(strict_types=1);

namespace Jpswade\LaravelDatabaseTools\Tests\Commands;

use Illuminate\Support\Facades\Config;
use Jpswade\LaravelDatabaseTools\Tests\TestCase;
use Symfony\Component\Console\Command\Command;

class DatabaseCreateCommandTest extends TestCase
{
    public function test_command_succeeds_with_valid_connection(): void
    {
        Config::set('database.default', 'testing');
        $this->artisan('db:create')
            ->expectsOutput('Done')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_command_fails_with_invalid_connection(): void
    {
        Config::set('database.default', 'invalid_connection');
        $this->expectException(\InvalidArgumentException::class);
        $this->artisan('db:create')->run();
    }

    public function test_command_outputs_expected_messages(): void
    {
        Config::set('database.default', 'testing');
        $this->artisan('db:create')
            ->expectsOutput('Done')
            ->assertExitCode(Command::SUCCESS);
    }
}
