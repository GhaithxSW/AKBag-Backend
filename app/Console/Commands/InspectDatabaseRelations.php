<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InspectDatabaseRelations extends Command
{
    protected $signature = 'db:inspect-relations';
    protected $description = 'Inspect database relationships and data integrity';

    public function handle()
    {
        $this->info('Inspecting database relationships and data integrity...');
        
        // Check tables
        $this->checkTable('albums');
        $this->checkTable('images');
        $this->checkTable('categories');
        
        // Check relationships
        $this->checkImageAlbumRelationships();
        $this->checkImageCategoryRelationships();
        
        return 0;
    }
    
    protected function checkTable($tableName)
    {
        $this->info("\n=== Table: {$tableName} ===");
        
        if (!Schema::hasTable($tableName)) {
            $this->error("  Table does not exist!");
            return;
        }
        
        // Get row count
        $count = DB::table($tableName)->count();
        $this->info("  Row count: {$count}");
        
        // Get column information
        $columns = Schema::getColumnListing($tableName);
        $this->info("  Columns: " . implode(', ', $columns));
        
        // Show sample data
        if ($count > 0) {
            $sample = DB::table($tableName)->first();
            $this->info("  Sample row:");
            foreach ((array)$sample as $key => $value) {
                if (is_string($value) && strlen($value) > 50) {
                    $value = substr($value, 0, 50) . '...';
                }
                $this->line("    {$key}: {$value}");
            }
        }
    }
    
    protected function checkImageAlbumRelationships()
    {
        $this->info("\n=== Checking Image-Album Relationships ===");
        
        // Count images with valid albums
        $validRelations = DB::table('images')
            ->join('albums', 'images.album_id', '=', 'albums.id')
            ->count();
            
        // Count images with invalid albums
        $invalidRelations = DB::table('images')
            ->leftJoin('albums', 'images.album_id', '=', 'albums.id')
            ->whereNull('albums.id')
            ->count();
            
        $totalImages = DB::table('images')->count();
        
        $this->info("  Total images: {$totalImages}");
        $this->info("  Images with valid albums: {$validRelations}");
        $this->info("  Images with invalid albums: {$invalidRelations}");
        
        // Show sample of invalid relationships
        if ($invalidRelations > 0) {
            $this->warn("  Sample of images with invalid albums:");
            $invalidImages = DB::table('images')
                ->leftJoin('albums', 'images.album_id', '=', 'albums.id')
                ->whereNull('albums.id')
                ->select('images.id', 'images.title', 'images.album_id')
                ->limit(3)
                ->get();
                
            foreach ($invalidImages as $image) {
                $this->line(sprintf("    Image #%d: '%s' (album_id: %s)", 
                    $image->id, 
                    $image->title,
                    $image->album_id ?? 'NULL'
                ));
            }
        }
    }
    
    protected function checkImageCategoryRelationships()
    {
        $this->info("\n=== Checking Image-Category Relationships ===");
        
        // Count images with categories
        $withCategory = DB::table('images')
            ->whereNotNull('category_id')
            ->count();
            
        // Count images without categories
        $withoutCategory = DB::table('images')
            ->whereNull('category_id')
            ->count();
            
        $totalImages = DB::table('images')->count();
        
        $this->info("  Total images: {$totalImages}");
        $this->info("  Images with category: {$withCategory}");
        $this->info("  Images without category: {$withoutCategory}");
        
        // Show sample of images without categories
        if ($withoutCategory > 0) {
            $this->warn("  Sample of images without categories:");
            $images = DB::table('images')
                ->whereNull('category_id')
                ->select('id', 'title')
                ->limit(3)
                ->get();
                
            foreach ($images as $image) {
                $this->line(sprintf("    Image #%d: '%s'", 
                    $image->id, 
                    $image->title
                ));
            }
        }
    }
}
