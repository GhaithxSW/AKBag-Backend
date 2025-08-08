<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckDbStructure extends Command
{
    protected $signature = 'db:check-structure';
    protected $description = 'Check database structure and relationships';

    public function handle()
    {
        $this->info('Checking database structure...');
        
        // Check if tables exist
        $tables = ['albums', 'images', 'categories'];
        foreach ($tables as $table) {
            $this->checkTable($table);
        }
        
        // Check relationships
        $this->checkRelationships();
        
        return 0;
    }
    
    protected function checkTable($tableName)
    {
        $this->info("\n=== Table: {$tableName} ===");
        
        if (!Schema::hasTable($tableName)) {
            $this->error("Table '{$tableName}' does not exist!");
            return;
        }
        
        // Get columns
        $columns = Schema::getColumnListing($tableName);
        $this->info("Columns: " . implode(', ', $columns));
        
        // Get row count
        $count = DB::table($tableName)->count();
        $this->info("Row count: {$count}");
        
        // Show sample data
        if ($count > 0) {
            $sample = DB::table($tableName)->first();
            $this->info("Sample row data:");
            foreach ((array)$sample as $key => $value) {
                if (is_string($value) && strlen($value) > 50) {
                    $value = substr($value, 0, 50) . '...';
                }
                $this->line(sprintf("  %-20s: %s", $key, $value));
            }
        }
    }
    
    protected function checkRelationships()
    {
        $this->info("\n=== Checking Relationships ===");
        
        // Check if images are linked to albums
        $this->info("\nImages with their albums:");
        try {
            $images = DB::table('images')
                ->join('albums', 'images.album_id', '=', 'albums.id')
                ->select('images.id', 'images.title as image_title', 'albums.id as album_id', 'albums.title as album_title')
                ->limit(5)
                ->get();
                
            if ($images->isNotEmpty()) {
                foreach ($images as $image) {
                    $this->line(sprintf("  Image #%d (%s) -> Album #%d (%s)", 
                        $image->id, 
                        $image->image_title,
                        $image->album_id,
                        $image->album_title
                    ));
                }
            } else {
                $this->warn("  No image-album relationships found!");
            }
        } catch (\Exception $e) {
            $this->error("  Error checking image-album relationships: " . $e->getMessage());
        }
        
        // Check if images are linked to categories
        $this->info("\nImages with their categories:");
        try {
            $images = DB::table('images')
                ->leftJoin('categories', 'images.category_id', '=', 'categories.id')
                ->select('images.id', 'images.title as image_title', 'categories.id as category_id', 'categories.name as category_name')
                ->limit(5)
                ->get();
                
            if ($images->isNotEmpty()) {
                foreach ($images as $image) {
                    $this->line(sprintf("  Image #%d (%s) -> Category #%d (%s)", 
                        $image->id, 
                        $image->image_title,
                        $image->category_id ?? 0,
                        $image->category_name ?? 'None'
                    ));
                }
            } else {
                $this->warn("  No image-category relationships found!");
            }
        } catch (\Exception $e) {
            $this->error("  Error checking image-category relationships: " . $e->getMessage());
        }
    }
}
