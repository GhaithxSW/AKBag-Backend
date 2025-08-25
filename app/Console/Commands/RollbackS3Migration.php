<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Image;
use Exception;

class RollbackS3Migration extends Command
{
    protected $signature = 'images:rollback-from-s3 {--dry-run : Show what would be rolled back without making changes} {--batch-size=10 : Number of images to process at once}';
    protected $description = 'Rollback S3 migration by downloading images back to local storage';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        
        $this->warn('🔄 Starting S3 migration rollback...');
        
        if ($dryRun) {
            $this->info('DRY RUN MODE - No files will be moved');
        } else {
            if (!$this->confirm('This will download all S3 images back to local storage. Continue?')) {
                $this->info('Rollback cancelled.');
                return;
            }
        }

        // Switch filesystem back to local first
        if (!$dryRun) {
            $this->updateFilesystemConfig();
        }

        // Get total count for progress tracking
        $totalImages = Image::whereNotNull('image_path')->count();
        
        if ($totalImages === 0) {
            $this->info('No images found to rollback.');
            return;
        }

        $this->info("Found {$totalImages} images to rollback from S3");
        
        $progressBar = $this->output->createProgressBar($totalImages);
        $progressBar->start();

        $successful = 0;
        $failed = 0;
        $skipped = 0;

        // Process images in chunks
        Image::whereNotNull('image_path')->chunk($batchSize, function ($images) use (&$successful, &$failed, &$skipped, $dryRun, $progressBar) {
            foreach ($images as $image) {
                try {
                    $result = $this->rollbackImage($image, $dryRun);
                    
                    switch ($result) {
                        case 'success':
                            $successful++;
                            break;
                        case 'skipped':
                            $skipped++;
                            break;
                        case 'failed':
                            $failed++;
                            break;
                    }
                    
                } catch (Exception $e) {
                    $this->error("\nFailed to rollback image ID {$image->id}: {$e->getMessage()}");
                    $failed++;
                }
                
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        
        $this->newLine(2);
        $this->info("Rollback completed:");
        $this->info("✅ Successful: {$successful}");
        $this->info("⏭️  Skipped: {$skipped}");
        $this->info("❌ Failed: {$failed}");

        if ($dryRun) {
            $this->warn('This was a dry run. No files were actually moved.');
            $this->info('Run without --dry-run to perform the actual rollback.');
        } else {
            $this->info('🔧 Remember to update your .env FILESYSTEM_DISK=public for full rollback');
        }
    }

    private function rollbackImage(Image $image, bool $dryRun = false): string
    {
        $imagePath = $image->image_path;
        
        // Check if file already exists locally
        if (Storage::disk('public')->exists($imagePath)) {
            return 'skipped';
        }
        
        // Check if file exists in S3
        if (!Storage::disk('s3')->exists($imagePath)) {
            $this->warn("\nS3 file not found for image ID {$image->id}: {$imagePath}");
            return 'failed';
        }
        
        if ($dryRun) {
            $this->line("\nWould rollback: S3:{$imagePath} -> local storage");
            return 'success';
        }

        try {
            // Download from S3
            $startTime = microtime(true);
            $fileContent = Storage::disk('s3')->get($imagePath);
            $downloadTime = microtime(true) - $startTime;
            
            if (!$fileContent) {
                throw new Exception("Failed to download from S3: {$imagePath}");
            }

            // Ensure local directory exists
            $directory = dirname($imagePath);
            if ($directory && !Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory, 0755, true);
            }

            // Save to local storage
            $saved = Storage::disk('public')->put($imagePath, $fileContent);
            
            if (!$saved) {
                throw new Exception("Failed to save to local storage: {$imagePath}");
            }

            // Verify local file
            if (!Storage::disk('public')->exists($imagePath)) {
                throw new Exception("File verification failed for local save: {$imagePath}");
            }
            
            // Verify file size matches
            $originalSize = strlen($fileContent);
            $localSize = Storage::disk('public')->size($imagePath);
            
            if ($originalSize !== $localSize) {
                throw new Exception("File size mismatch. S3: {$originalSize}, Local: {$localSize}");
            }
            
            // Log slow downloads
            if ($downloadTime > 5) {
                $this->warn("\nSlow download detected for image ID {$image->id}: {$downloadTime}s");
            }

            return 'success';
            
        } catch (Exception $e) {
            throw new Exception("Rollback failed for {$imagePath}: " . $e->getMessage());
        }
    }

    private function updateFilesystemConfig()
    {
        $this->info('📝 To complete rollback, update your .env file:');
        $this->info('   FILESYSTEM_DISK=public');
        $this->warn('⚠️  Manual step required: Update .env file after rollback completes');
    }
}