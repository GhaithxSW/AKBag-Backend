<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckDb extends Command
{
    protected $signature = 'db:check';
    protected $description = 'Check database structure and data integrity';

    public function handle()
    {
        $this->info('Checking database structure and data integrity...');
        
        // Check tables
        $this->checkTable('albums');
        $this->checkTable('images');
        $this->checkTable('categories');
        
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
        
        // Check images -> albums relationship
        $this->info("\nChecking images -> albums relationship...");
        $imagesWithoutAlbum = DB::table('images')
            ->leftJoin('albums', 'images.album_id', '=', 'albums.id')
            ->whereNull('albums.id')
            ->count();
            
        $this->info("Images without a valid album: {$imagesWithoutAlbum}");
        
        // Check images -> categories relationship
        $this->info("\nChecking images -> categories relationship...");
        $imagesWithoutCategory = DB::table('images')
            ->whereNull('category_id')
            ->count();
            
        $this->info("Images without a category: {$imagesWithoutCategory}");
        
        // Show sample of images with their albums and categories
        $this->info("\nSample of images with their albums and categories:");
        $images = DB::table('images')
            ->leftJoin('albums', 'images.album_id', '=', 'albums.id')
            ->leftJoin('categories', 'images.category_id', '=', 'categories.id')
            ->select(
                'images.id as image_id',
                'images.title as image_title',
                'albums.id as album_id',
                'albums.title as album_title',
                'categories.id as category_id',
                'categories.name as category_name'
            )
            ->limit(5)
            ->get();
            
        if ($images->isNotEmpty()) {
            foreach ($images as $image) {
                $this->line(sprintf(
                    "Image #%d (%s) -> Album #%d (%s), Category #%d (%s)",
                    $image->image_id,
                    $image->image_title,
                    $image->album_id,
                    $image->album_title,
                    $image->category_id ?? 0,
                    $image->category_name ?? 'None'
                ));
            }
        } else {
            $this->warn("No images found!");
        }
    }
}
