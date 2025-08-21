<?php

namespace App\Console\Commands;

use App\Services\YupooService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestYupooConnection extends Command
{
    protected $signature = 'yupoo:test';
    protected $description = 'Test connection to Yupoo and basic functionality';

    protected $yupooService;

    public function __construct(YupooService $yupooService)
    {
        parent::__construct();
        $this->yupooService = $yupooService;
    }

    public function handle()
    {
        // Enable error reporting for debugging
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        // Set up error logging to a file
        $logFile = storage_path('logs/yupoo_test_' . date('Y-m-d_His') . '.log');
        $logStream = fopen($logFile, 'w');
        
        // Redirect errors to the log file
        set_error_handler(function($errno, $errstr, $errfile, $errline) use ($logStream) {
            fwrite($logStream, "Error [$errno] $errstr in $errfile on line $errline\n");
            return false;
        });
        
        try {
            $this->runTest();
            
            // Restore error handlers
            restore_error_handler();
            
            $this->info("✅ Yupoo connection test completed successfully!");
            
            // Close the log file
            if (is_resource($logStream)) {
                fclose($logStream);
            }
            
            return 0;
        } catch (\Exception $e) {
            // Log the error
            fwrite($logStream, "Fatal error: " . $e->getMessage() . "\n");
            fwrite($logStream, $e->getTraceAsString() . "\n");
            
            // Close the log file
            if (is_resource($logStream)) {
                fclose($logStream);
            }
            
            $this->error("❌ Error in test: " . $e->getMessage());
            $this->error("   File: " . $e->getFile() . ":" . $e->getLine());
            $this->error("   Trace: " . $e->getTraceAsString());
            
            return 1;
        }
    }
    
    protected function runTest()
    {
        $this->info('=== Yupoo Connection Test ===');
        $this->line('');
        
        // Test with a known Yupoo URL
        $testUrl = 'https://297228164.x.yupoo.com/albums';
        
        $this->line("1. Testing URL: <fg=blue>{$testUrl}</>");
        
        // Check if URL is accessible
        if (!filter_var($testUrl, FILTER_VALIDATE_URL)) {
            $this->error("❌ Invalid URL format: {$testUrl}");
            return 1;
        }
        
        // Check if we can resolve the domain
        $host = parse_url($testUrl, PHP_URL_HOST);
        $this->line("   Resolving DNS for: {$host}");
        
        try {
            $ip = gethostbyname($host);
            if ($ip === $host) {
                $this->error("❌ Failed to resolve DNS for: {$host}");
                return 1;
            }
            $this->line("   ✅ Resolved to IP: {$ip}");
        } catch (\Exception $e) {
            $this->error("❌ DNS resolution failed: " . $e->getMessage());
            return 1;
        }
        
        $this->info("✅ URL format is valid");
        $this->line('');
        
        $this->line("2. Testing connection to Yupoo...");
        
        try {
            // Test basic HTTP connection
            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 30,
                'connect_timeout' => 10,
                'http_errors' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'DNT' => '1',
                ]
            ]);
            
            $this->line("   Sending HTTP request to Yupoo...");
            
            try {
                $response = $client->get($testUrl);
                $statusCode = $response->getStatusCode();
                
                $this->line("   Response status: {$statusCode}");
                
                // Log response headers
                $this->line("\n   Response headers:");
                foreach ($response->getHeaders() as $name => $values) {
                    $this->line("     {$name}: " . implode(', ', $values));
                }
                
                if ($statusCode !== 200) {
                    $this->error("❌ Failed to connect to Yupoo. Status code: {$statusCode}");
                    // Save the response body for debugging
                    $debugFile = 'yupoo_error_response_' . time() . '.html';
                    \Storage::disk('local')->put($debugFile, (string)$response->getBody());
                    $this->line("   Saved error response to: storage/app/{$debugFile}");
                    return 1;
                }
                
                // Check for redirects
                $effectiveUrl = $response->effectiveUri() ?? $testUrl;
                if ($effectiveUrl != $testUrl) {
                    $this->warn("⚠️  Request was redirected to: {$effectiveUrl}");
                }
                
                $this->info("✅ Successfully connected to Yupoo (Status: {$statusCode})");
                
                // Save the response for debugging
                $html = (string) $response->getBody();
                $debugFile = 'yupoo_test_response_' . time() . '.html';
                \Storage::disk('local')->put($debugFile, $html);
                $this->line("   Saved response to: storage/app/{$debugFile}");
                
                // Check for common Yupoo structures
                $hasJsonData = strpos($html, 'window.__INITIAL_STATE__') !== false;
                $hasAlbumElements = strpos($html, 'album') !== false || strpos($html, 'photo') !== false;
                
                $this->line('');
                $this->line("3. Analyzing page structure:");
                
            } catch (\Exception $e) {
                $this->error("❌ Error making HTTP request: " . $e->getMessage());
                return 1;
            }
            $this->line("   - Contains JSON data: " . ($hasJsonData ? '✅ Yes' : '❌ No'));
            $this->line("   - Contains album/photo elements: " . ($hasAlbumElements ? '✅ Yes' : '❌ No'));
            
            // Extract a sample of the HTML for debugging
            $sample = substr($html, 0, 1000);
            $this->line('');
            $this->line("4. HTML Sample (first 1000 chars):");
            $this->line("<fg=gray>" . htmlspecialchars($sample) . "</>");
            
            $this->line('');
            $this->line("5. Attempting to fetch albums using YupooService...");
            
            // Now test the YupooService
            $startTime = microtime(true);
            $albums = $this->yupooService->fetchAlbums($testUrl, 1, 1);
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($albums === null) {
                $this->error("❌ fetchAlbums returned null");
                return 1;
            }
            
            if (!is_array($albums)) {
                $this->error("❌ Expected array from fetchAlbums, got: " . gettype($albums));
                return 1;
            }
            
            if (empty($albums)) {
                $this->warn("⚠️  No albums found in the response");
                return 0;
            }
            
            $this->info(sprintf("✅ Successfully fetched %d album(s) in %sms", count($albums), $duration));
            
            // Display first album details
            $firstAlbum = $albums[0];
            $this->line('');
            $this->line("6. First album details:");
            $this->line("   Title: <fg=cyan>" . ($firstAlbum['title'] ?? 'N/A') . "</>");
            $this->line("   URL: <fg=blue>" . ($firstAlbum['url'] ?? 'N/A') . "</>");
            
            // Test fetching images from the first album
            if (!empty($firstAlbum['url'])) {
                $this->line('');
                $this->line("7. Testing image fetch for the first album...");
                
                try {
                    $startTime = microtime(true);
                    $images = $this->yupooService->fetchAlbumImages($firstAlbum['url']);
                    $duration = round((microtime(true) - $startTime) * 1000, 2);
                    
                    if (is_array($images)) {
                        $this->info(sprintf("✅ Successfully fetched %d image(s) in %sms", count($images), $duration));
                        
                        if (!empty($images)) {
                            $firstImage = $images[0];
                            $this->line('');
                            $this->line("8. First image details:");
                            $this->line("   Title: <fg=cyan>" . ($firstImage['title'] ?? 'N/A') . "</>");
                            $this->line("   URL: <fg=blue>" . ($firstImage['url'] ?? 'N/A') . "</>");
                            
                            // Test downloading the first image
                            $this->line('');
                            $this->line("9. Testing image download...");
                            
                            try {
                                $startTime = microtime(true);
                                $path = $this->yupooService->downloadImage($firstImage['url'], 'test');
                                $duration = round((microtime(true) - $startTime) * 1000, 2);
                                
                                if ($path) {
                                    $size = round(filesize(storage_path('app/public/' . $path)) / 1024, 2);
                                    $this->info(sprintf("✅ Successfully downloaded image to: %s (%.2f KB in %sms)", 
                                        $path, $size, $duration));
                                } else {
                                    $this->warn("⚠️  Image download returned empty path");
                                }
                            } catch (\Exception $e) {
                                $this->warn("⚠️  Image download test skipped: " . $e->getMessage());
                            }
                        }
                    } else {
                        $this->warn("⚠️  No images found in the album");
                    }
                } catch (\Exception $e) {
                    $this->warn("⚠️  Error fetching album images: " . $e->getMessage());
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error in test: " . $e->getMessage());
            $this->error("   File: " . $e->getFile() . ":" . $e->getLine());
            $this->error("   Trace: " . $e->getTraceAsString());
            return 1;
        }
        
        $this->info('\n✅ Yupoo connection test completed successfully!');
        return 0;
    }
}
