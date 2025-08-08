<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YupooService;
use Illuminate\Support\Facades\Log;

class SimpleYupooTest extends Command
{
    protected $signature = 'yupoo:simple-test {--debug}';
    protected $description = 'A simplified test for Yupoo service';

    protected $yupooService;
    protected $debug = false;

    public function __construct(YupooService $yupooService)
    {
        parent::__construct();
        $this->yupooService = $yupooService;
    }

    public function handle()
    {
        $this->debug = $this->option('debug');
        $this->info('=== Simple Yupoo Service Test ===');
        
        try {
            // Test if the service can be instantiated
            $this->info('1. Testing YupooService instantiation...');
            $this->info('   ✅ YupooService instantiated successfully');
            
            // Test a simple HTTP request
            $this->info('\n2. Testing HTTP request to Yupoo...');
            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'DNT' => '1',
                ]
            ]);
            
            $response = $client->get('https://297228164.x.yupoo.com/albums');
            $statusCode = $response->getStatusCode();
            
            if ($statusCode === 200) {
                $this->info('   ✅ Successfully connected to Yupoo');
                $html = (string) $response->getBody();
                $this->info('   Response length: ' . strlen($html) . ' bytes');
                
                if ($this->debug) {
                    $debugFile = storage_path('logs/yupoo_debug_' . date('Ymd_His') . '.html');
                    file_put_contents($debugFile, $html);
                    $this->info('   Debug HTML saved to: ' . $debugFile);
                }
                
                // Test fetching albums
                $this->testFetchAlbums();
                
            } else {
                $this->error("   ❌ Failed to connect to Yupoo. Status code: {$statusCode}");
                return 1;
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());
            
            if ($this->debug) {
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }
            
            Log::error('Yupoo test failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return 1;
        }
    }
    
    protected function testFetchAlbums()
    {
        $this->info('\n3. Testing YupooService::fetchAlbums()...');
        
        try {
            $startTime = microtime(true);
            $albums = $this->yupooService->fetchAlbums('https://297228164.x.yupoo.com/albums', 1, 2);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            if (!is_array($albums)) {
                throw new \Exception('Expected array from fetchAlbums, got ' . gettype($albums));
            }
            
            $this->info(sprintf('   ✅ Fetched %d album(s) in %sms', count($albums), $duration));
            
            if (empty($albums)) {
                $this->warn('   ⚠️  No albums found');
                return;
            }
            
            // Display first album info
            $album = $albums[0];
            $this->info('\n4. First album details:');
            $this->line('   Title: ' . ($album['title'] ?? 'N/A'));
            $this->line('   URL: ' . ($album['url'] ?? 'N/A'));
            
            // Test fetching images for the first album
            if (!empty($album['url'])) {
                $this->testFetchAlbumImages($album['url']);
            }
            
        } catch (\Exception $e) {
            $this->error('   ❌ Failed to fetch albums: ' . $e->getMessage());
            if ($this->debug) {
                $this->error('   ' . $e->getTraceAsString());
            }
            throw $e;
        }
    }
    
    protected function testFetchAlbumImages($albumUrl)
    {
        $this->info('\n5. Testing YupooService::fetchAlbumImages()...');
        
        try {
            $startTime = microtime(true);
            $images = $this->yupooService->fetchAlbumImages($albumUrl);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            if (!is_array($images)) {
                throw new \Exception('Expected array from fetchAlbumImages, got ' . gettype($images));
            }
            
            $this->info(sprintf('   ✅ Fetched %d image(s) in %sms', count($images), $duration));
            
            if (empty($images)) {
                $this->warn('   ⚠️  No images found in the album');
                return;
            }
            
            // Display first image info
            $image = $images[0];
            $this->info('\n6. First image details:');
            $this->line('   Title: ' . ($image['title'] ?? 'N/A'));
            $this->line('   URL: ' . ($image['url'] ?? 'N/A'));
            
            // Test downloading the first image
            $this->testDownloadImage($image['url'] ?? '');
            
        } catch (\Exception $e) {
            $this->error('   ❌ Failed to fetch album images: ' . $e->getMessage());
            if ($this->debug) {
                $this->error('   ' . $e->getTraceAsString());
            }
            throw $e;
        }
    }
    
    protected function testDownloadImage($imageUrl)
    {
        if (empty($imageUrl)) {
            $this->warn('\n7. Skipping image download - no image URL provided');
            return;
        }
        
        $this->info('\n7. Testing YupooService::downloadImage()...');
        
        try {
            $startTime = microtime(true);
            $path = $this->yupooService->downloadImage($imageUrl, 'test');
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($path) {
                $fullPath = storage_path('app/public/' . ltrim($path, '/'));
                $size = file_exists($fullPath) ? filesize($fullPath) : 0;
                $this->info(sprintf('   ✅ Downloaded image to: %s (%.2f KB in %sms)', 
                    $path, $size / 1024, $duration));
            } else {
                $this->warn('   ⚠️  Image download returned empty path');
            }
            
        } catch (\Exception $e) {
            $this->error('   ❌ Failed to download image: ' . $e->getMessage());
            if ($this->debug) {
                $this->error('   ' . $e->getTraceAsString());
            }
            throw $e;
        }
    }
}
