<?php

namespace Jpswade\LaravelDatabaseTools\Tests\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Jpswade\LaravelDatabaseTools\Tests\TestCase;

class DatabaseOptimizeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        DB::statement('CREATE TABLE test_table (id INT PRIMARY KEY)');
    }

    public function tearDown(): void
    {
        DB::statement('DROP TABLE test_table');
        parent::tearDown();
    }

    public function testShowsUnsupportedMessageWhenUsingSQLite()
    {
        Artisan::call('db:optimize');
        $output = Artisan::output();
        $this->assertStringContainsString('Optimization is only supported for MySQL databases.', $output);
    }

    public function testShowsUnsupportedMessageWhenUsingSpecificTable()
    {
        Artisan::call('db:optimize', ['--table' => ['test_table']]);
        $output = Artisan::output();
        $this->assertStringContainsString('Optimization is only supported for MySQL databases.', $output);
    }

    public function testShowsUnsupportedMessageWhenUsingNonExistentTable()
    {
        Artisan::call('db:optimize', ['--table' => ['non_existent_table']]);
        $output = Artisan::output();
        $this->assertStringContainsString('Optimization is only supported for MySQL databases.', $output);
    }

    public function testShowsUnsupportedMessageWhenUsingSpecificDatabase()
    {
        Artisan::call('db:optimize', ['--database' => 'testing']);
        $output = Artisan::output();
        $this->assertStringContainsString('Optimization is only supported for MySQL databases.', $output);
    }

    public function testShowsUnsupportedMessageWhenUsingNonExistentDatabase()
    {
        Artisan::call('db:optimize', ['--database' => 'non_existent_database']);
        $output = Artisan::output();
        $this->assertStringContainsString('Optimization is only supported for MySQL databases.', $output);
    }

    public function testOptimizesTablesWithMySQLDriver()
    {
        // Only run this test if MySQL is available
        if (getenv('DB_CONNECTION') === 'mysql') {
            \Illuminate\Support\Facades\Config::set('database.connections.testing.driver', 'mysql');
            Artisan::call('db:optimize');
            $output = Artisan::output();
            $this->assertStringContainsString('Starting Optimizing database tables', $output);
            $this->assertStringContainsString('Optimization Completed', $output);
        } else {
            $this->markTestSkipped('MySQL connection not available for testing.');
        }
    }
}
