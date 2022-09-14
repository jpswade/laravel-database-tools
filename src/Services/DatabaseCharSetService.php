<?php

namespace Jpswade\LaravelDatabaseTools\Services;

use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use Symfony\Component\Console\Style\OutputStyle;

/**
 * Updates the database tables to the charset and collation that you have configured.
 * @see https://gist.github.com/NBZ4live/04d5981eaf0244b57d0296b381e04195
 */
class DatabaseCharSetService
{
    public function __invoke(DatabaseManager $db, string $databaseName, string $charset, string $collation, OutputStyle $output)
    {
        $this->validate($databaseName, $charset, $collation);
        $output->info("Changing the database {$databaseName} default charset to {$charset} and collation to {$collation}");
        $db->unprepared("ALTER SCHEMA {$databaseName} DEFAULT CHARACTER SET {$charset} DEFAULT COLLATE {$collation};");

        $output->info('Getting the list of all tables...');
        $tableNames = $db->table('information_schema.tables')
            ->where('table_schema', $databaseName)->get(['TABLE_NAME'])->pluck('TABLE_NAME');

        $output->info('Iterate through the list and alter each table');
        foreach ($tableNames as $tableName) {
            $db->unprepared("ALTER TABLE {$tableName} CONVERT TO CHARACTER SET {$charset} COLLATE {$collation};");
        }

        $output->info('Getting the list of all columns that have a collation...');
        $columns = $db->table('information_schema.columns')
            ->where('table_schema', $databaseName)
            ->whereNotNull('COLLATION_NAME')
            ->get();

        $output->info('Iterating through the list and alter each column...');
        foreach ($columns as $column) {
            $tableName = $column->TABLE_NAME;
            $columnName = $column->COLUMN_NAME;
            $columnType = $column->COLUMN_TYPE;

            $null = 'DEFAULT NULL';
            if ($column->IS_NULLABLE == 'NO') {
                $null = 'NOT NULL';
            }

            $sql = "ALTER TABLE {$tableName}
                    CHANGE `{$columnName}` `{$columnName}`
                    {$columnType}
                    CHARACTER SET {$charset}
                    COLLATE {$collation}
                    {$null}";
            $db->unprepared($sql);
        }
    }

    protected function validate(string $schemaName, string $charset, string $collation): void
    {
        if (empty($schemaName)) {
            throw new InvalidArgumentException('Database Name is not set');
        }
        if (empty($charset)) {
            throw new InvalidArgumentException('Charset is not set');
        }
        if (empty($collation)) {
            throw new InvalidArgumentException('Collation is not set');
        }
    }
}