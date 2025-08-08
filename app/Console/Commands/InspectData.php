<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\Image;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectData extends Command
{
    protected $signature = 'inspect:data';
    protected $description = 'Inspect the data in the database for any issues';

    public function handle()
    {
        $this->info('Starting data inspection...');
        
        // Check albums
        $this->checkAlbums();
        
        // Check images
        $this->checkImages();
        
        // Check categories
        $this->checkCategories();
        
        $this->info('\nData inspection complete.');
        
        return 0;
    }
    
    protected function checkAlbums()
    {
        $this->info("\n=== Albums ===");
        
        // Get total count
        $total = Album::count();
        $this->info("Total albums: {$total}");
        
        if ($total === 0) {
            $this->warn('No albums found in the database!');
            return;
        }
        
        // Get sample of albums
        $albums = Album::orderBy('id', 'asc')->limit(5)->get();
        
        $this->info("\nSample albums (first 5):");
        foreach ($albums as $album) {
            $this->line(sprintf(
                "- ID: %d, Title: %s, Collection ID: %d, Created: %s",
                $album->id,
                $album->title,
                $album->collection_id,
                $album->created_at
            ));
        }
        
        // Check for albums without images
        $albumsWithoutImages = Album::doesntHave('images')->count();
        $this->info("\nAlbums without images: {$albumsWithoutImages}");
        
        // Check for albums with invalid collection references
        $invalidCollections = DB::table('albums')
            ->leftJoin('collections', 'albums.collection_id', '=', 'collections.id')
            ->whereNull('collections.id')
            ->count();
            
        if ($invalidCollections > 0) {
            $this->warn("WARNING: Found {$invalidCollections} albums with invalid collection references!");
        }
    }
    
    protected function checkImages()
    {
        $this->info("\n=== Images ===");
        
        // Get total count
        $total = Image::count();
        $this->info("Total images: {$total}");
        
        if ($total === 0) {
            $this->warn('No images found in the database!');
            return;
        }
        
        // Get sample of images
        $images = Image::with(['album', 'category'])
            ->orderBy('id', 'asc')
            ->limit(5)
            ->get();
        
        $this->info("\nSample images (first 5):");
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
        $withoutCategory = Image::whereNull('category_id')->count();
        $this->info("\nImages without a category: {$withoutCategory}");
        
        // Check for images with invalid album references
        $invalidAlbums = DB::table('images')
            ->leftJoin('albums', 'images.album_id', '=', 'albums.id')
            ->whereNull('albums.id')
            ->count();
            
        if ($invalidAlbums > 0) {
            $this->warn("WARNING: Found {$invalidAlbums} images with invalid album references!");
        }
        
        // Check for images with invalid category references
        $invalidCategories = DB::table('images')
            ->whereNotNull('category_id')
            ->leftJoin('categories', 'images.category_id', '=', 'categories.id')
            ->whereNull('categories.id')
            ->count();
            
        if ($invalidCategories > 0) {
            $this->warn("WARNING: Found {$invalidCategories} images with invalid category references!");
        }
    }
    
    protected function checkCategories()
    {
        $this->info("\n=== Categories ===");
        
        // Get total count
        $total = Category::count();
        $this->info("Total categories: {$total}");
        
        if ($total === 0) {
            $this->warn('No categories found in the database!');
            return;
        }
        
        // Get sample of categories
        $categories = Category::withCount('images')
            ->orderBy('id', 'asc')
            ->limit(5)
            ->get();
        
        $this->info("\nSample categories (first 5):");
        foreach ($categories as $category) {
            $this->line(sprintf(
                "- ID: %d, Name: %s, Image Count: %d",
                $category->id,
                $category->name,
                $category->images_count
            ));
        }
        
        // Check for unused categories
        $unusedCategories = Category::doesntHave('images')->count();
        $this->info("\nCategories without any images: {$unusedCategories}");
    }
}
