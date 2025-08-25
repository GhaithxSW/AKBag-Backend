<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDOException;

class CheckDatabaseConnection extends Command
{
    protected $signature = 'db:check-connection';

    protected $description = 'Check the database connection and list tables';

    public function handle()
    {
        $this->info('Checking database connection...');

        try {
            // Test connection
            DB::connection()->getPdo();
            $this->info('✅ Database connection successful!');

            // Get database name
            $database = DB::connection()->getDatabaseName();
            $this->info("\n📊 Database: {$database}");

            // List all tables
            $tables = DB::select('SHOW TABLES');
            $tableNames = array_map('current', json_decode(json_encode($tables), true));

            $this->info("\n📋 Tables (".count($tableNames).'):');
            foreach ($tableNames as $table) {
                $rowCount = DB::table($table)->count();
                $this->line("- {$table} ({$rowCount} records)");
            }

            return 0;

        } catch (PDOException $e) {
            $this->error('❌ Could not connect to the database.');
            $this->line('Error: '.$e->getMessage());

            return 1;
        }
    }
}
