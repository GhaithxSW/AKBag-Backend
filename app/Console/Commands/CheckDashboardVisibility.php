<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\Image;
use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDashboardVisibility extends Command
{
    protected $signature = 'dashboard:check-visibility';
    protected $description = 'Check dashboard visibility of albums, images, and categories';

    public function handle()
    {
        $this->info('Checking dashboard visibility...');
        
        // Check albums visibility
        $this->checkAlbumsVisibility();
        
        // Check images visibility
        $this->checkImagesVisibility();
        
        // Check categories visibility
        $this->checkCategoriesVisibility();
        
        return 0;
    }
    
    protected function checkAlbumsVisibility()
    {
        $this->info("\n=== Albums Visibility ===");
        
        $totalAlbums = Album::count();
        $this->info("Total albums in database: {$totalAlbums}");
        
        // Check for any visibility-related columns
        $album = new Album();
        $hasVisibility = in_array('is_visible', $album->getFillable()) || 
                        in_array('is_published', $album->getFillable());
        
        if ($hasVisibility) {
            $visibleAlbums = Album::where('is_visible', true)->orWhere('is_published', true)->count();
            $hiddenAlbums = $totalAlbums - $visibleAlbums;
            $this->info("Visible albums: {$visibleAlbums}");
            $this->info("Hidden albums: {$hiddenAlbums}");
            
            if ($hiddenAlbums > 0) {
                $this->warn("  Some albums may be hidden by visibility settings");
            }
        } else {
            $this->info("No visibility settings found for albums");
        }
        
        // Check for any date-based visibility
        $now = now();
        $futureAlbums = Album::where('published_at', '>', $now)
            ->orWhere('visible_from', '>', $now)
            ->orWhere('visible_until', '<', $now)
            ->count();
            
        if ($futureAlbums > 0) {
            $this->warn("  {$futureAlbums} albums may be hidden due to date restrictions");
        }
    }
    
    protected function checkImagesVisibility()
    {
        $this->info("\n=== Images Visibility ===");
        
        $totalImages = Image::count();
        $this->info("Total images in database: {$totalImages}");
        
        // Check for any visibility-related columns
        $image = new Image();
        $hasVisibility = in_array('is_visible', $image->getFillable()) || 
                        in_array('is_published', $image->getFillable());
        
        if ($hasVisibility) {
            $visibleImages = Image::where('is_visible', true)->orWhere('is_published', true)->count();
            $hiddenImages = $totalImages - $visibleImages;
            $this->info("Visible images: {$visibleImages}");
            $this->info("Hidden images: {$hiddenImages}");
            
            if ($hiddenImages > 0) {
                $this->warn("  Some images may be hidden by visibility settings");
            }
        } else {
            $this->info("No visibility settings found for images");
        }
        
        // Check for images without categories
        $imagesWithoutCategory = Image::whereNull('category_id')->count();
        if ($imagesWithoutCategory > 0) {
            $this->warn("  {$imagesWithoutCategory} images do not have a category assigned");
        }
        
        // Check for images with invalid album references
        $imagesWithInvalidAlbum = DB::table('images')
            ->leftJoin('albums', 'images.album_id', '=', 'albums.id')
            ->whereNull('albums.id')
            ->count();
            
        if ($imagesWithInvalidAlbum > 0) {
            $this->error("  {$imagesWithInvalidAlbum} images have invalid album references");
        }
    }
    
    protected function checkCategoriesVisibility()
    {
        $this->info("\n=== Categories Visibility ===");
        
        $totalCategories = Category::count();
        $this->info("Total categories in database: {$totalCategories}");
        
        // Check for any visibility-related columns
        $category = new Category();
        $hasVisibility = in_array('is_visible', $category->getFillable()) || 
                        in_array('is_active', $category->getFillable());
        
        if ($hasVisibility) {
            $visibleCategories = Category::where('is_visible', true)
                ->orWhere('is_active', true)
                ->count();
                
            $hiddenCategories = $totalCategories - $visibleCategories;
            $this->info("Visible categories: {$visibleCategories}");
            $this->info("Hidden categories: {$hiddenCategories}");
            
            if ($hiddenCategories > 0) {
                $this->warn("  Some categories may be hidden by visibility settings");
            }
        } else {
            $this->info("No visibility settings found for categories");
        }
        
        // Check for unused categories
        $usedCategories = Image::select('category_id')
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id')
            ->toArray();
            
        $unusedCategories = Category::whereNotIn('id', $usedCategories)->count();
        if ($unusedCategories > 0) {
            $this->warn("  {$unusedCategories} categories are not assigned to any images");
        }
    }
}
