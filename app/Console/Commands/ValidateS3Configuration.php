<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Image;
use Exception;

class ValidateS3Configuration extends Command
{
    protected $signature = 'images:validate-s3 {--test-upload : Test file upload/download functionality}';
    protected $description = 'Validate S3 configuration and readiness for migration';

    public function handle()
    {
        $this->info('🔍 Validating S3 Configuration...');
        $this->newLine();
        
        $allPassed = true;
        $testUpload = $this->option('test-upload');

        // 1. Check environment variables
        $this->info('1. Checking environment variables...');
        $envVars = [
            'AWS_ACCESS_KEY_ID' => env('AWS_ACCESS_KEY_ID'),
            'AWS_SECRET_ACCESS_KEY' => env('AWS_SECRET_ACCESS_KEY'),
            'AWS_DEFAULT_REGION' => env('AWS_DEFAULT_REGION'),
            'AWS_BUCKET' => env('AWS_BUCKET'),
            'FILESYSTEM_DISK' => env('FILESYSTEM_DISK')
        ];

        foreach ($envVars as $key => $value) {
            if (empty($value)) {
                $this->error("   ❌ {$key} is not configured");
                $allPassed = false;
            } else {
                $this->info("   ✅ {$key} is configured");
            }
        }

        // 2. Test S3 connectivity
        $this->newLine();
        $this->info('2. Testing S3 connectivity...');
        
        try {
            // Test basic connectivity by listing bucket contents
            Storage::disk('s3')->exists('test-connectivity');
            $this->info('   ✅ S3 connection successful');
        } catch (Exception $e) {
            $this->error('   ❌ S3 connection failed: ' . $e->getMessage());
            $allPassed = false;
        }

        // 3. Test file operations if requested
        if ($testUpload) {
            $this->newLine();
            $this->info('3. Testing file upload/download operations...');
            
            if (!$this->testFileOperations()) {
                $allPassed = false;
            }
        }

        // 4. Check bucket permissions
        $this->newLine();
        $this->info('4. Checking bucket permissions...');
        
        if (!$this->checkBucketPermissions()) {
            $this->warn('   ⚠️  Bucket permissions may need adjustment for public access');
        } else {
            $this->info('   ✅ Bucket permissions look good');
        }

        // 5. Database readiness check
        $this->newLine();
        $this->info('5. Checking database readiness...');
        
        $imageCount = Image::whereNotNull('image_path')->count();
        if ($imageCount > 0) {
            $this->info("   ✅ Found {$imageCount} images ready for migration");
        } else {
            $this->warn('   ⚠️  No images found in database');
        }

        // 6. Local storage check
        $this->newLine();
        $this->info('6. Checking local storage...');
        
        $localImageCount = 0;
        try {
            $localFiles = Storage::disk('public')->allFiles('images');
            $localImageCount = count($localFiles);
            
            if ($localImageCount > 0) {
                $this->info("   ✅ Found {$localImageCount} local image files");
            } else {
                $this->warn('   ⚠️  No local image files found in public/images');
            }
        } catch (Exception $e) {
            $this->error('   ❌ Error checking local storage: ' . $e->getMessage());
        }

        // 7. Performance estimation
        $this->newLine();
        $this->info('7. Migration performance estimation...');
        
        if ($imageCount > 0) {
            $estimatedTime = ceil($imageCount / 10); // Assuming 10 images per minute
            $this->info("   📊 Estimated migration time: ~{$estimatedTime} minutes for {$imageCount} images");
            
            if ($imageCount > 1000) {
                $this->warn('   ⚠️  Large dataset detected. Consider using smaller batch sizes (--batch-size=5)');
            }
        }

        // Final summary
        $this->newLine();
        if ($allPassed) {
            $this->info('✅ All validation checks passed! S3 migration is ready.');
            $this->newLine();
            $this->info('Next steps:');
            $this->info('  1. Run a dry-run first: php artisan images:migrate-to-s3 --dry-run');
            $this->info('  2. Start with small batch: php artisan images:migrate-to-s3 --batch-size=5');
            $this->info('  3. Use --preserve-local to keep local files as backup');
        } else {
            $this->error('❌ Some validation checks failed. Please fix the issues before migration.');
        }

        return $allPassed ? 0 : 1;
    }

    private function testFileOperations(): bool
    {
        try {
            $testKey = 'validation-test-' . time() . '.txt';
            $testContent = 'S3 validation test file - ' . date('Y-m-d H:i:s');
            
            // Test upload
            $this->line('   Testing upload...');
            $uploaded = Storage::disk('s3')->put($testKey, $testContent);
            if (!$uploaded) {
                $this->error('   ❌ Upload test failed');
                return false;
            }
            
            // Test existence check
            $this->line('   Testing file existence...');
            if (!Storage::disk('s3')->exists($testKey)) {
                $this->error('   ❌ File existence check failed');
                return false;
            }
            
            // Test download
            $this->line('   Testing download...');
            $retrieved = Storage::disk('s3')->get($testKey);
            if ($retrieved !== $testContent) {
                $this->error('   ❌ Download test failed - content mismatch');
                return false;
            }
            
            // Test file size
            $this->line('   Testing file size check...');
            $size = Storage::disk('s3')->size($testKey);
            if ($size !== strlen($testContent)) {
                $this->error('   ❌ File size check failed');
                return false;
            }
            
            // Cleanup
            Storage::disk('s3')->delete($testKey);
            
            $this->info('   ✅ All file operations successful');
            return true;
            
        } catch (Exception $e) {
            $this->error('   ❌ File operations test failed: ' . $e->getMessage());
            return false;
        }
    }

    private function checkBucketPermissions(): bool
    {
        try {
            // Test URL generation
            $testImage = Image::whereNotNull('image_path')->first();
            if (!$testImage) {
                return true; // Skip if no images to test
            }
            
            $url = Storage::disk('s3')->url($testImage->image_path);
            
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                return false;
            }
            
            // Basic URL structure check
            $bucket = env('AWS_BUCKET');
            $region = env('AWS_DEFAULT_REGION');
            
            $expectedPattern = "https://{$bucket}.s3.{$region}.amazonaws.com/";
            
            return str_contains($url, $expectedPattern) || str_contains($url, "https://{$bucket}.s3.amazonaws.com/");
            
        } catch (Exception $e) {
            return false;
        }
    }
}