<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Image;
use Exception;

class MigrateImagesToS3 extends Command
{
    protected $signature = 'images:migrate-to-s3 {--dry-run : Show what would be migrated without making changes} {--batch-size=10 : Number of images to process at once} {--skip-validation : Skip pre-migration validation checks} {--preserve-local : Keep local files after migration}';
    protected $description = 'Migrate existing images from local storage to S3';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        $skipValidation = $this->option('skip-validation');
        $preserveLocal = $this->option('preserve-local');
        
        $this->info('Starting image migration to S3...');
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will be moved');
        }

        // Pre-migration validation
        if (!$skipValidation) {
            $this->info('Running pre-migration validation...');
            if (!$this->validateS3Configuration()) {
                $this->error('Pre-migration validation failed. Use --skip-validation to bypass checks.');
                return 1;
            }
            $this->info('✅ Pre-migration validation passed');
        }

        // Get total count first for memory efficiency
        $totalImages = Image::whereNotNull('image_path')->count();
        
        if ($totalImages === 0) {
            $this->info('No images found to migrate.');
            return;
        }

        $this->info("Found {$totalImages} images to migrate");
        
        $progressBar = $this->output->createProgressBar($totalImages);
        $progressBar->start();

        $successful = 0;
        $failed = 0;
        $skipped = 0;

        // Process images in chunks to prevent memory exhaustion
        Image::whereNotNull('image_path')->chunk($batchSize, function ($images) use (&$successful, &$failed, &$skipped, $dryRun, $preserveLocal, $progressBar) {
            foreach ($images as $image) {
                try {
                    $result = $this->migrateImage($image, $dryRun, $preserveLocal);
                    
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
                    $this->error("\nFailed to migrate image ID {$image->id}: {$e->getMessage()}");
                    $failed++;
                }
                
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        
        $this->newLine(2);
        $this->info("Migration completed:");
        $this->info("✅ Successful: {$successful}");
        $this->info("⏭️  Skipped: {$skipped}");
        $this->info("❌ Failed: {$failed}");

        if ($dryRun) {
            $this->warn('This was a dry run. No files were actually moved.');
            $this->info('Run without --dry-run to perform the actual migration.');
        }
    }

    private function validateS3Configuration(): bool
    {
        try {
            // Test AWS credentials and bucket access
            $this->line('Validating AWS credentials and bucket access...');
            
            // Test basic S3 connectivity
            $testKey = 'migration-test-' . time() . '.txt';
            $testContent = 'S3 migration test file';
            
            // Upload test file
            $uploaded = Storage::disk('s3')->put($testKey, $testContent);
            if (!$uploaded) {
                $this->error('Failed to upload test file to S3');
                return false;
            }
            
            // Verify file exists
            if (!Storage::disk('s3')->exists($testKey)) {
                $this->error('Test file was not found in S3 after upload');
                return false;
            }
            
            // Test file retrieval
            $retrieved = Storage::disk('s3')->get($testKey);
            if ($retrieved !== $testContent) {
                $this->error('Retrieved content does not match uploaded content');
                return false;
            }
            
            // Clean up test file
            Storage::disk('s3')->delete($testKey);
            
            // Test public URL generation (for bucket permissions)
            $this->line('Testing S3 URL generation...');
            $testImage = Image::whereNotNull('image_path')->first();
            if ($testImage) {
                $url = $testImage->image_url; // Use the model accessor instead
                if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                    $this->warn('Warning: S3 URL generation may have issues. Generated URL: ' . $url);
                    return $this->confirm('Continue with migration despite URL generation warning?');
                }
                $this->info('Generated URL: ' . $url);
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->error('S3 validation failed: ' . $e->getMessage());
            return false;
        }
    }

    private function migrateImage(Image $image, bool $dryRun = false, bool $preserveLocal = false): string
    {
        $localPath = $image->image_path;
        
        // Check if file exists in S3 already
        if (Storage::disk('s3')->exists($localPath)) {
            return 'skipped';
        }
        
        // Try to find the file in local storage
        $fileContent = null;
        $sourceFound = false;
        
        // Try public disk first
        if (Storage::disk('public')->exists($localPath)) {
            $fileContent = Storage::disk('public')->get($localPath);
            $sourceFound = true;
        } 
        // Try local disk
        elseif (Storage::disk('local')->exists($localPath)) {
            $fileContent = Storage::disk('local')->get($localPath);
            $sourceFound = true;
        }
        // Try variations of the path
        elseif (Storage::disk('public')->exists("images/{$localPath}")) {
            $fileContent = Storage::disk('public')->get("images/{$localPath}");
            $sourceFound = true;
            $localPath = "images/{$localPath}";
        }
        elseif (Storage::disk('public')->exists("yupoo/images/{$localPath}")) {
            $fileContent = Storage::disk('public')->get("yupoo/images/{$localPath}");
            $sourceFound = true;
            $localPath = "yupoo/images/{$localPath}";
        }

        if (!$sourceFound || !$fileContent) {
            $this->warn("\nLocal file not found for image ID {$image->id}: {$localPath}");
            return 'failed';
        }

        if ($dryRun) {
            $this->line("\nWould migrate: {$localPath} -> S3");
            return 'success';
        }

        // Upload to S3 with timeout handling
        $startTime = microtime(true);
        $uploaded = Storage::disk('s3')->put($image->image_path, $fileContent);
        $uploadTime = microtime(true) - $startTime;
        
        if (!$uploaded) {
            throw new Exception("Failed to upload to S3: {$localPath}");
        }

        // Enhanced verification with file integrity
        if (!Storage::disk('s3')->exists($image->image_path)) {
            throw new Exception("File verification failed for S3 upload: {$image->image_path}");
        }
        
        // Verify file size matches
        $originalSize = strlen($fileContent);
        $s3Size = Storage::disk('s3')->size($image->image_path);
        
        if ($originalSize !== $s3Size) {
            throw new Exception("File size mismatch. Original: {$originalSize}, S3: {$s3Size}");
        }
        
        // Log successful upload with metrics
        if ($uploadTime > 5) {
            $this->warn("\nSlow upload detected for image ID {$image->id}: {$uploadTime}s");
        }
        
        // Optional: Remove local file after successful migration
        if (!$preserveLocal && !$dryRun) {
            try {
                if (Storage::disk('public')->exists($localPath)) {
                    Storage::disk('public')->delete($localPath);
                } elseif (Storage::disk('local')->exists($localPath)) {
                    Storage::disk('local')->delete($localPath);
                }
            } catch (Exception $e) {
                // Log but don't fail the migration if local cleanup fails
                $this->warn("\nFailed to remove local file for image ID {$image->id}: {$e->getMessage()}");
            }
        }

        return 'success';
    }
}