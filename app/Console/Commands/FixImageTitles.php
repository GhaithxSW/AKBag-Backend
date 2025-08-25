<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Image;
use App\Services\YupooService;

class FixImageTitles extends Command
{
    protected $signature = 'yupoo:fix-image-titles 
                           {--dry-run : Show what would be changed without making actual changes}
                           {--limit= : Limit the number of images to process}';

    protected $description = 'Fix existing images that have generic titles like "Imported from Yupoo"';

    protected $yupooService;

    public function __construct(YupooService $yupooService)
    {
        parent::__construct();
        $this->yupooService = $yupooService;
    }

    public function handle()
    {
        $this->info('🔍 Searching for images with generic titles...');
        
        $isDryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        
        // Find images with generic titles
        $query = Image::where(function($q) {
            $q->where('title', 'like', '%Imported from Yupoo%')
              ->orWhere('title', 'like', '%Image %')
              ->orWhere('title', 'like', '%Untitled%')
              ->orWhere('title', '')
              ->orWhereNull('title');
        });
        
        if ($limit) {
            $query->limit((int) $limit);
        }
        
        $images = $query->get();
        
        if ($images->isEmpty()) {
            $this->info('✅ No images found with generic titles.');
            return 0;
        }
        
        $this->info("📊 Found {$images->count()} images with generic titles.");
        
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }
        
        $this->newLine();
        
        $bar = $this->output->createProgressBar($images->count());
        $bar->setFormat('%current%/%max% [%bar%] %percent:3s%% %message%');
        
        $fixedCount = 0;
        $skippedCount = 0;
        
        foreach ($images as $image) {
            $bar->setMessage("Processing: {$image->title}");
            
            // Try to extract a meaningful name
            $newTitle = $this->generateBetterTitle($image);
            
            if ($newTitle && $newTitle !== $image->title) {
                if (!$isDryRun) {
                    $image->update(['title' => $newTitle]);
                }
                $fixedCount++;
                
                if ($this->option('verbose') || $isDryRun) {
                    $this->newLine();
                    $this->line("  📝 '{$image->title}' → '{$newTitle}'");
                    if (!$isDryRun) {
                        $this->line("     💾 Updated in database");
                    }
                }
            } else {
                $skippedCount++;
                if ($this->option('verbose')) {
                    $this->newLine();
                    $this->line("  ⏭️  Skipped: {$image->title} (no better title found)");
                }
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        // Summary
        if ($isDryRun) {
            $this->info("📋 DRY RUN SUMMARY:");
            $this->info("  • Would fix: {$fixedCount} images");
            $this->info("  • Would skip: {$skippedCount} images");
            $this->info("  • Run without --dry-run to apply changes");
        } else {
            $this->info("✅ COMPLETED:");
            $this->info("  • Fixed: {$fixedCount} images");
            $this->info("  • Skipped: {$skippedCount} images");
        }
        
        return 0;
    }
    
    /**
     * Generate a better title for an image
     */
    protected function generateBetterTitle(Image $image): ?string
    {
        // Strategy 1: Extract from original_url if available
        if (!empty($image->original_url)) {
            $nameFromUrl = $this->extractImageNameFromUrl($image->original_url);
            if ($nameFromUrl) {
                return $nameFromUrl;
            }
        }
        
        // Strategy 2: Extract from image_path
        if (!empty($image->image_path)) {
            $nameFromPath = $this->extractImageNameFromUrl($image->image_path);
            if ($nameFromPath) {
                return $nameFromPath;
            }
        }
        
        // Strategy 3: Use album title + index if available
        if ($image->album && $image->album->title) {
            $albumTitle = $image->album->title;
            $imageIndex = $image->album->images()->where('id', '<=', $image->id)->count();
            return "{$albumTitle} - Image " . str_pad($imageIndex, 3, '0', STR_PAD_LEFT);
        }
        
        // Strategy 4: Use collection name + index if available
        if ($image->album && $image->album->collection && $image->album->collection->name) {
            $collectionName = $image->album->collection->name;
            $imageIndex = Image::whereHas('album', function($q) use ($image) {
                $q->where('collection_id', $image->album->collection_id);
            })->where('id', '<=', $image->id)->count();
            return "{$collectionName} - Image " . str_pad($imageIndex, 3, '0', STR_PAD_LEFT);
        }
        
        // Strategy 5: Generic but meaningful fallback
        return "Image " . str_pad($image->id, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Extract a meaningful image name from URL (reuse YupooService logic)
     */
    protected function extractImageNameFromUrl($url): ?string
    {
        if (empty($url)) {
            return null;
        }
        
        // Parse URL to get the path
        $path = parse_url($url, PHP_URL_PATH);
        if (empty($path)) {
            return null;
        }
        
        // Get filename without extension
        $filename = pathinfo($path, PATHINFO_FILENAME);
        
        // Skip if it's just a hash or meaningless ID
        if (preg_match('/^[a-f0-9]{8,}$/i', $filename) || 
            preg_match('/^(img|image|photo|pic)_?\d*$/i', $filename) ||
            strlen($filename) < 3) {
            return null;
        }
        
        // Clean up common Yupoo patterns
        $filename = preg_replace('/^(\d+)_[a-z0-9]+$/i', '$1', $filename);
        $filename = preg_replace('/_[a-z]$/', '', $filename);
        
        // Convert to readable format
        $name = str_replace(['_', '-', '.'], ' ', $filename);
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        
        // Skip if still looks like a hash or ID
        if (preg_match('/^\d{8,}$/', str_replace(' ', '', $name))) {
            return null;
        }
        
        return ucwords($name);
    }
}