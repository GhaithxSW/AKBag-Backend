<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckDbSchema extends Command
{
    protected $signature = 'db:check-schema';
    protected $description = 'Check database schema and relationships';

    public function handle()
    {
        $this->checkTable('albums');
        $this->checkTable('images');
        $this->checkTable('categories');
        
        $this->checkRelationships();
        
        return 0;
    }
    
    protected function checkTable($tableName)
    {
        $this->info("\n=== Checking table: {$tableName} ===");
        
        if (!Schema::hasTable($tableName)) {
            $this->error("Table '{$tableName}' does not exist!");
            return;
        }
        
        // Get column information
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
        
        // Check albums with images
        $albumsWithImages = DB::table('albums')
            ->join('images', 'albums.id', '=', 'images.album_id')
            ->select('albums.id as album_id', 'albums.title as album_title', DB::raw('COUNT(images.id) as image_count'))
            ->groupBy('albums.id', 'albums.title')
            ->get();
            
        $this->info("\nAlbums with image counts:");
        if ($albumsWithImages->isNotEmpty()) {
            foreach ($albumsWithImages as $album) {
                $this->line(sprintf("  Album #%d (%s): %d images", 
                    $album->album_id, 
                    $album->album_title,
                    $album->image_count
                ));
            }
        } else {
            $this->warn("  No albums with images found!");
        }
        
        // Check images with categories
        $imagesWithCategories = DB::table('images')
            ->leftJoin('categories', 'images.category_id', '=', 'categories.id')
            ->select('images.id as image_id', 'images.title as image_title', 'categories.name as category_name')
            ->limit(5)
            ->get();
            
        $this->info("\nSample images with categories:");
        if ($imagesWithCategories->isNotEmpty()) {
            foreach ($imagesWithCategories as $image) {
                $this->line(sprintf("  Image #%d (%s): Category - %s", 
                    $image->image_id,
                    $image->image_title,
                    $image->category_name ?? 'None'
                ));
            }
        } else {
            $this->warn("  No images with categories found!");
        }
    }
}
