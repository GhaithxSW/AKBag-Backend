<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\Image;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDataIntegrity extends Command
{
    protected $signature = 'check:data-integrity';
    protected $description = 'Check the integrity of albums, images, and categories data';

    public function handle()
    {
        $this->info('Checking data integrity...');
        
        // Check albums
        $this->checkAlbums();
        
        // Check images
        $this->checkImages();
        
        // Check categories
        $this->checkCategories();
        
        return 0;
    }
    
    protected function checkAlbums()
    {
        $this->info("\n=== Checking Albums ===");
        
        $totalAlbums = Album::count();
        $this->info("Total albums: {$totalAlbums}");
        
        if ($totalAlbums > 0) {
            // Get a sample of albums with their relationships
            $albums = Album::with(['images', 'collection'])
                ->withCount('images')
                ->orderBy('id', 'asc')
                ->limit(5)
                ->get();
                
            $this->info("Sample albums with relationships:");
            foreach ($albums as $album) {
                $this->line(sprintf(
                    "- ID: %d, Title: %s, Collection: %s, Images: %d",
                    $album->id,
                    $album->title,
                    $album->collection ? $album->collection->name : 'None',
                    $album->images_count
                ));
            }
        }
    }
    
    protected function checkImages()
    {
        $this->info("\n=== Checking Images ===");
        
        $totalImages = Image::count();
        $this->info("Total images: {$totalImages}");
        
        if ($totalImages > 0) {
            // Get a sample of images with their relationships
            $images = Image::with(['album', 'category'])
                ->orderBy('id', 'asc')
                ->limit(5)
                ->get();
                
            $this->info("Sample images with relationships:");
            foreach ($images as $image) {
                $this->line(sprintf(
                    "- ID: %d, Title: %s, Album: %s, Category: %s",
                    $image->id,
                    $image->title,
                    $image->album ? $image->album->title : 'None',
                    $image->category ? $image->category->name : 'None'
                ));
            }
            
            // Check for images without categories
            $imagesWithoutCategory = Image::whereNull('category_id')->count();
            $this->info("\nImages without a category: {$imagesWithoutCategory}");
            
            // Check for images with invalid album references
            $imagesWithInvalidAlbum = DB::table('images')
                ->leftJoin('albums', 'images.album_id', '=', 'albums.id')
                ->whereNull('albums.id')
                ->count();
                
            if ($imagesWithInvalidAlbum > 0) {
                $this->warn("\nWARNING: Found {$imagesWithInvalidAlbum} images with invalid album references!");
            }
        }
    }
    
    protected function checkCategories()
    {
        $this->info("\n=== Checking Categories ===");
        
        $totalCategories = Category::count();
        $this->info("Total categories: {$totalCategories}");
        
        if ($totalCategories > 0) {
            // Get a sample of categories with their relationships
            $categories = Category::withCount('images')
                ->orderBy('id', 'asc')
                ->limit(5)
                ->get();
                
            $this->info("Sample categories with image counts:");
            foreach ($categories as $category) {
                $this->line(sprintf(
                    "- ID: %d, Name: %s, Images: %d",
                    $category->id,
                    $category->name,
                    $category->images_count
                ));
            }
            
            // Check for unused categories
            $unusedCategories = Category::doesntHave('images')->count();
            if ($unusedCategories > 0) {
                $this->warn("\nWARNING: Found {$unusedCategories} categories without any images!");
            }
        }
    }
}
