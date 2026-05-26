<?php

declare(strict_types=1);

namespace Jpswade\LaravelDatabaseTools\Tests\Commands;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;
use Jpswade\LaravelDatabaseTools\Commands\DatabaseDumpCommand;
use Jpswade\LaravelDatabaseTools\Tests\TestCase;
use Spatie\DbDumper\Databases\MySql;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Process\Process;

class DatabaseDumpCommandTest extends TestCase
{
    /**
     * Regression test for the TypeError raised when DB_PORT (a string in
     * .env) was passed straight to Spatie\DbDumper\DbDumper::setPort(), which
     * requires int.
     */
    public function test_command_casts_string_port_to_int(): void
    {
        Config::set('dbtools.database', [
            'host' => 'db.example.com',
            'port' => '3306',
            'database' => 'roundtab',
            'username' => 'roundtab',
            'password' => 'secret',
        ]);

        $capturing = new CapturingDumpCommand;
        $capturing->setLaravel($this->app);
        $this->app[Kernel::class]->registerCommand($capturing);

        $this->artisan('db:dump')->assertExitCode(Command::SUCCESS);

        self::assertSame('db.example.com', $capturing->capturedHost);
        self::assertIsInt($capturing->capturedPort);
        self::assertSame(3306, $capturing->capturedPort);
        self::assertSame('roundtab', $capturing->capturedDatabase);
        self::assertSame('roundtab', $capturing->capturedUsername);
        self::assertSame('secret', $capturing->capturedPassword);
    }

    public function test_command_fails_when_required_config_is_missing(): void
    {
        Config::set('dbtools.database', [
            'host' => '',
            'port' => '3306',
            'database' => 'roundtab',
            'username' => 'roundtab',
        ]);

        $this->artisan('db:dump')
            ->expectsOutputToContain('Missing configuration fields')
            ->assertExitCode(Command::FAILURE);
    }
}

class CapturingDumpCommand extends DatabaseDumpCommand
{
    public ?string $capturedHost = null;

    public ?int $capturedPort = null;

    public ?string $capturedDatabase = null;

    public ?string $capturedUsername = null;

    public ?string $capturedPassword = null;

    protected function makeDumper(string $host, int $port, string $database, string $username, string $password): MySql
    {
        $this->capturedHost = $host;
        $this->capturedPort = $port;
        $this->capturedDatabase = $database;
        $this->capturedUsername = $username;
        $this->capturedPassword = $password;

        return MySql::create();
    }

    /**
     * @param  resource  $tempFileHandle
     */
    protected function dumpToFile(MySql $mysqlDumper, string $dumpFile, $tempFileHandle): Process
    {
        $directory = dirname($dumpFile);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        file_put_contents($dumpFile, "-- fake dump\n");

        return Process::fromShellCommandline('true');
    }
}
