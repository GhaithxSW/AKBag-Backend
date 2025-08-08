<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDatabaseState extends Command
{
    protected $signature = 'db:check-state';
    protected $description = 'Check the current state of the database';

    public function handle()
    {
        try {
            // Check albums with their relationships
            $this->info("\n=== Checking Albums with Relationships ===");
            $albumCount = DB::table('albums')->count();
            $this->info("Total albums: " . $albumCount);
            
            if ($albumCount > 0) {
                // Get albums with their image counts
                $albums = DB::table('albums')
                    ->leftJoin('images', 'albums.id', '=', 'images.album_id')
                    ->select([
                        'albums.id',
                        'albums.title',
                        'albums.created_at',
                        DB::raw('COUNT(images.id) as image_count')
                    ])
                    ->groupBy('albums.id', 'albums.title', 'albums.created_at')
                    ->limit(3)
                    ->get();
                
                $this->info("Sample albums with image counts:");
                foreach ($albums as $album) {
                    $this->line(sprintf("- ID: %d, Title: %s, Images: %d, Created: %s", 
                        $album->id, 
                        $album->title,
                        $album->image_count,
                        $album->created_at
                    ));
                }
            }
            
            // Check images with their relationships
            $this->info("\n=== Checking Images with Album Info ===");
            $imageCount = DB::table('images')->count();
            $this->info("Total images: " . $imageCount);
            
            if ($imageCount > 0) {
                $images = DB::table('images')
                    ->join('albums', 'images.album_id', '=', 'albums.id')
                    ->leftJoin('categories', 'images.category_id', '=', 'categories.id')
                    ->select([
                        'images.id',
                        'images.album_id',
                        'albums.title as album_title',
                        'images.title as image_title',
                        'images.path',
                        'categories.name as category_name'
                    ])
                    ->limit(3)
                    ->get();
                
                $this->info("Sample images with album and category info:");
                foreach ($images as $image) {
                    $this->line(sprintf("- ID: %d, Album: %s, Title: %s, Category: %s, Path: %s", 
                        $image->id,
                        $image->album_title,
                        $image->image_title,
                        $image->category_name ?? 'None',
                        $image->path
                    ));
                }
            }
            
            // Check categories with image counts
            $this->info("\n=== Checking Categories with Image Counts ===");
            $categoryCount = DB::table('categories')->count();
            $this->info("Total categories: " . $categoryCount);
            
            if ($categoryCount > 0) {
                $categories = DB::table('categories')
                    ->leftJoin('images', 'categories.id', '=', 'images.category_id')
                    ->select([
                        'categories.id',
                        'categories.name',
                        'categories.created_at',
                        DB::raw('COUNT(images.id) as image_count')
                    ])
                    ->groupBy('categories.id', 'categories.name', 'categories.created_at')
                    ->limit(3)
                    ->get();
                
                $this->info("Sample categories with image counts:");
                foreach ($categories as $category) {
                    $this->line(sprintf("- ID: %d, Name: %s, Images: %d, Created: %s", 
                        $category->id,
                        $category->name,
                        $category->image_count,
                        $category->created_at
                    ));
                }
            }
            
            $this->info("\nDatabase check completed!");
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Error checking database: " . $e->getMessage());
            return 1;
        }
    }
}
