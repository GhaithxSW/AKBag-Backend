<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectDbRelationships extends Command
{
    protected $signature = 'db:inspect-relationships';
    protected $description = 'Inspect database relationships using raw SQL queries';

    public function handle()
    {
        $this->info('Inspecting database relationships...');
        
        // Get database type
        $dbType = DB::getDriverName();
        $this->info("Database type: {$dbType}");
        
        // Get list of tables
        $tables = $this->getTables();
        $this->info("\nTables in database: " . implode(', ', $tables));
        
        // Inspect each table
        foreach ($tables as $table) {
            $this->inspectTable($table);
        }
        
        // Check relationships
        $this->checkRelationships();
        
        return 0;
    }
    
    protected function getTables()
    {
        $dbType = DB::getDriverName();
        
        if ($dbType === 'sqlite') {
            $result = DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
            return collect($result)->pluck('name')->toArray();
        } elseif ($dbType === 'mysql') {
            $result = DB::select('SHOW TABLES');
            $tables = [];
            foreach ($result as $table) {
                $tables[] = $table->{array_keys((array)$table)[0]};
            }
            return $tables;
        } else {
            $this->error("Unsupported database type: {$dbType}");
            return [];
        }
    }
    
    protected function inspectTable($tableName)
    {
        $this->info("\n=== Table: {$tableName} ===");
        
        try {
            // Get row count
            $count = DB::table($tableName)->count();
            $this->info("Row count: {$count}");
            
            if ($count > 0) {
                // Get column names
                $firstRow = (array) DB::table($tableName)->first();
                $columns = array_keys($firstRow);
                $this->info("Columns: " . implode(', ', $columns));
                
                // Show sample data
                $this->info("Sample data (first row):");
                foreach ($firstRow as $key => $value) {
                    if (is_string($value) && strlen($value) > 50) {
                        $value = substr($value, 0, 50) . '...';
                    }
                    $this->line(sprintf("  %-20s: %s", $key, $value));
                }
            }
        } catch (\Exception $e) {
            $this->error("  Error inspecting table: " . $e->getMessage());
        }
    }
    
    protected function checkRelationships()
    {
        $this->info("\n=== Checking Relationships ===");
        
        // Check albums and images
        $this->checkTableRelationship('images', 'album_id', 'albums', 'id');
        
        // Check images and categories
        $this->checkTableRelationship('images', 'category_id', 'categories', 'id', 'LEFT');
    }
    
    protected function checkTableRelationship($fromTable, $fromColumn, $toTable, $toColumn, $joinType = 'INNER')
    {
        $this->info("\nChecking relationship: {$fromTable}.{$fromColumn} -> {$toTable}.{$toColumn}");
        
        try {
            // Check if columns exist
            $fromColumns = $this->getTableColumns($fromTable);
            $toColumns = $this->getTableColumns($toTable);
            
            if (!in_array($fromColumn, $fromColumns)) {
                $this->error("  Column {$fromTable}.{$fromColumn} does not exist!");
                return;
            }
            
            if (!in_array($toColumn, $toColumns)) {
                $this->error("  Column {$toTable}.{$toColumn} does not exist!");
                return;
            }
            
            // Get sample of relationships
            $results = DB::select("
                SELECT 
                    f.{$fromColumn} as from_id,
                    t.{$toColumn} as to_id,
                    COUNT(*) as count
                FROM 
                    {$fromTable} f
                {$joinType} JOIN 
                    {$toTable} t ON f.{$fromColumn} = t.{$toColumn}
                GROUP BY 
                    f.{$fromColumn}, t.{$toColumn}
                ORDER BY 
                    count DESC
                LIMIT 5
            ");
            
            if (empty($results)) {
                $this->warn("  No relationships found!");
                return;
            }
            
            $this->info("Sample relationships (top 5 by count):");
            foreach ($results as $result) {
                $this->line(sprintf("  %s.%s = %d -> %s.%s = %s (count: %d)",
                    $fromTable,
                    $fromColumn,
                    $result->from_id,
                    $toTable,
                    $toColumn,
                    $result->to_id ?? 'NULL',
                    $result->count
                ));
            }
            
        } catch (\Exception $e) {
            $this->error("  Error checking relationship: " . $e->getMessage());
        }
    }
    
    protected function getTableColumns($tableName)
    {
        try {
            $dbType = DB::getDriverName();
            
            if ($dbType === 'sqlite') {
                $results = DB::select("PRAGMA table_info({$tableName})");
                return array_column($results, 'name');
            } elseif ($dbType === 'mysql') {
                $results = DB::select("SHOW COLUMNS FROM {$tableName}");
                return array_column($results, 'Field');
            } else {
                $this->error("Unsupported database type: {$dbType}");
                return [];
            }
        } catch (\Exception $e) {
            $this->error("  Error getting columns for table {$tableName}: " . $e->getMessage());
            return [];
        }
    }
}
