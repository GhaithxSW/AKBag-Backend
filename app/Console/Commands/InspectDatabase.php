<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InspectDatabase extends Command
{
    protected $signature = 'db:inspect {table? : The table to inspect}';

    protected $description = 'Inspect database tables and their contents';

    public function handle()
    {
        $table = $this->argument('table');

        if ($table) {
            $this->inspectTable($table);
        } else {
            $this->listTables();
        }

        return 0;
    }

    protected function listTables()
    {
        $tables = [];
        $dbType = DB::getDriverName();

        if ($dbType === 'sqlite') {
            $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"))
                ->pluck('name')
                ->toArray();
        } elseif ($dbType === 'mysql') {
            $tables = collect(DB::select('SHOW TABLES'))
                ->map(function ($table) {
                    return $table->{array_keys((array) $table)[0]};
                })
                ->toArray();
        } else {
            $this->warn("Unsupported database type: {$dbType}");

            return;
        }

        $this->info("Available tables in database (using {$dbType}):");

        foreach ($tables as $tableName) {
            try {
                $rowCount = DB::table($tableName)->count();
                $this->line("- {$tableName} (rows: {$rowCount})");
            } catch (\Exception $e) {
                $this->line("- {$tableName} (error: ".$e->getMessage().')');
            }
        }
    }

    protected function inspectTable($tableName)
    {
        try {
            if (! Schema::hasTable($tableName)) {
                $this->error("Table '{$tableName}' does not exist.");

                return;
            }

            $this->info("\n=== Table: {$tableName} ===");

            // Get column information
            $columns = Schema::getColumnListing($tableName);
            $this->info("\nColumns: ".implode(', ', $columns));

            // Get row count
            $rowCount = DB::table($tableName)->count();
            $this->info("Total rows: {$rowCount}");

            if ($rowCount > 0) {
                // Get sample data with limited columns to avoid memory issues
                $sampleData = DB::table($tableName)->limit(3)->get();

                $this->info("\nSample data (first 3 rows):");

                foreach ($sampleData as $rowIndex => $row) {
                    $this->line("\n".str_repeat('-', 50));
                    $this->line('Row #'.($rowIndex + 1));

                    foreach ($row as $key => $value) {
                        if (is_string($value) && strlen($value) > 50) {
                            $value = substr($value, 0, 50).'...';
                        }
                        $this->line(sprintf('%-15s: %s', $key, $value));
                    }
                }

                $this->line(str_repeat('-', 50));

                // Show sample of specific columns that might be important for relationships
                $importantColumns = array_intersect($columns, ['id', 'album_id', 'title', 'name']);
                if (count($importantColumns) > 0) {
                    $this->info("\nSample IDs and relationships (first 5 rows):");
                    $sampleRelations = DB::table($tableName)
                        ->select($importantColumns)
                        ->limit(5)
                        ->get()
                        ->map(function ($item) {
                            return (array) $item;
                        })
                        ->toArray();

                    // Manually build the table
                    $headers = $importantColumns;
                    $rows = [];
                    foreach ($sampleRelations as $relation) {
                        $row = [];
                        foreach ($importantColumns as $col) {
                            $row[] = $relation[$col] ?? 'NULL';
                        }
                        $rows[] = $row;
                    }

                    $this->table($headers, $rows);
                }
            }
        } catch (\Exception $e) {
            $this->error("Error inspecting table '{$tableName}': ".$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());
        }
    }
}
