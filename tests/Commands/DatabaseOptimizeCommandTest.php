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

    public function it_optimizes_all_tables_when_no_table_option_is_provided()
    {
        Artisan::call('db:optimize');

        $output = Artisan::output();

        $this->assertStringContainsString('Starting Optimization.', $output);
        $this->assertStringContainsString('Optimization Completed', $output);
    }

    public function it_optimizes_specific_tables_when_table_option_is_provided()
    {
        Artisan::call('db:optimize', ['--table' => 'test_table']);

        $output = Artisan::output();

        $this->assertStringContainsString('Starting Optimization.', $output);
        $this->assertStringContainsString('Optimization Completed', $output);
    }

    public function it_does_not_optimize_tables_when_non_existent_table_option_is_provided()
    {
        Artisan::call('db:optimize', ['--table' => 'non_existent_table']);

        $output = Artisan::output();

        $this->assertStringContainsString('Starting Optimization.', $output);
        $this->assertStringNotContainsString('Optimization Completed', $output);
    }

    public function it_optimizes_tables_in_specific_database_when_database_option_is_provided()
    {
        Artisan::call('db:optimize', ['--database' => 'test_database']);

        $output = Artisan::output();

        $this->assertStringContainsString('Starting Optimization.', $output);
        $this->assertStringContainsString('Optimization Completed', $output);
    }

    public function it_does_not_optimize_tables_when_non_existent_database_option_is_provided()
    {
        Artisan::call('db:optimize', ['--database' => 'non_existent_database']);

        $output = Artisan::output();

        $this->assertStringContainsString('Starting Optimization.', $output);
        $this->assertStringNotContainsString('Optimization Completed', $output);
    }
}
