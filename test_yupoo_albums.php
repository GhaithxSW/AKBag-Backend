<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

function log_message($message) {
    echo "[" . date('Y-m-d H:i:s') . "] $message\n";
}

// Function to make HTTP requests
function make_request($client, $url) {
    try {
        $response = $client->get($url, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Referer' => 'https://x.yupoo.com/',
            ],
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
        
        return [
            'status' => $response->getStatusCode(),
            'body' => (string) $response->getBody(),
            'headers' => $response->getHeaders(),
        ];
    } catch (\Exception $e) {
        log_message("Error: " . $e->getMessage());
        return null;
    }
}

try {
    $client = new Client([
        'verify' => false, // Disable SSL verification for testing
        'allow_redirects' => [
            'max' => 5,
            'strict' => true,
            'referer' => true,
            'protocols' => ['http', 'https'],
            'track_redirects' => true
        ],
        'cookies' => true, // Enable cookies to maintain session
    ]);

    // Test 1: Fetch the main page
    $yupooUrl = 'https://297228164.x.yupoo.com';
    log_message("Testing connection to: $yupooUrl");
    
    $response = make_request($client, $yupooUrl);
    
    if (!$response) {
        throw new Exception("Failed to fetch the main page");
    }
    
    log_message("Status: {$response['status']}");
    
    // Save the response for inspection
    $debugFile = storage_path('logs/yupoo_main_page.html');
    file_put_contents($debugFile, $response['body']);
    log_message("Main page saved to: $debugFile");
    
    // Try to extract album links
    $albumLinks = [];
    if (preg_match_all('/<a[^>]+href=["\']([^"\']+albums\/\d+)["\']/i', $response['body'], $matches)) {
        $albumLinks = array_unique($matches[1]);
    }
    
    if (empty($albumLinks)) {
        log_message("No album links found in the main page. Trying alternative method...");
        
        // Try to find JSON data
        if (preg_match('/<script[^>]*>\s*window\.__INITIAL_STATE__\s*=\s*({.+?})\s*<\/script>/is', $response['body'], $jsonMatches)) {
            $jsonData = json_decode($jsonMatches[1], true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                log_message("Found JSON data in the page");
                file_put_contents(storage_path('logs/yupoo_initial_state.json'), json_encode($jsonData, JSON_PRETTY_PRINT));
                
                // Try to extract album links from JSON
                if (isset($jsonData['albumList']['list'])) {
                    foreach ($jsonData['albumList']['list'] as $album) {
                        if (isset($album['id'])) {
                            $albumLinks[] = "/albums/" . $album['id'];
                        }
                    }
                }
            }
        }
    }
    
    if (empty($albumLinks)) {
        throw new Exception("Could not find any album links in the page");
    }
    
    log_message("Found " . count($albumLinks) . " album links");
    
    // Test the first album
    $albumPath = $albumLinks[0];
    $albumUrl = 'https://297228164.x.yupoo.com' . (strpos($albumPath, '/') === 0 ? '' : '/') . $albumPath;
    
    log_message("\nTesting album: $albumUrl");
    
    $albumResponse = make_request($client, $albumUrl);
    
    if (!$albumResponse) {
        throw new Exception("Failed to fetch the album page");
    }
    
    log_message("Album Status: {$albumResponse['status']}");
    
    // Save album response
    $albumFile = storage_path('logs/yupoo_album.html');
    file_put_contents($albumFile, $albumResponse['body']);
    log_message("Album page saved to: $albumFile");
    
    // Try to extract image URLs
    $imageUrls = [];
    
    // Method 1: Look for JSON data
    if (preg_match('/<script[^>]*>\s*window\.__INITIAL_STATE__\s*=\s*({.+?})\s*<\/script>/is', $albumResponse['body'], $jsonMatches)) {
        $jsonData = json_decode($jsonMatches[1], true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            log_message("Found JSON data in album page");
            file_put_contents(storage_path('logs/yupoo_album_data.json'), json_encode($jsonData, JSON_PRETTY_PRINT));
            
            // Try to extract image URLs from JSON
            if (isset($jsonData['albumDetail']['photoList'])) {
                foreach ($jsonData['albumDetail']['photoList'] as $photo) {
                    if (isset($photo['url'])) {
                        $imageUrls[] = $photo['url'];
                    }
                }
            }
        }
    }
    
    // Method 2: Look for image tags
    if (empty($imageUrls) && preg_match_all('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|gif|webp))[^"\']*["\']/i', $albumResponse['body'], $imgMatches)) {
        $imageUrls = array_merge($imageUrls, $imgMatches[1]);
    }
    
    // Clean and filter image URLs
    $imageUrls = array_unique($imageUrls);
    $imageUrls = array_filter($imageUrls, function($url) {
        // Filter out common non-image URLs
        return !preg_match('/(logo|icon|avatar|placeholder|spinner|loading|advertisement)/i', $url);
    });
    
    log_message("\nFound " . count($imageUrls) . " unique image URLs");
    
    // Display first few image URLs
    $count = min(5, count($imageUrls));
    for ($i = 0; $i < $count; $i++) {
        log_message(sprintf("  %d. %s", $i + 1, $imageUrls[$i]));
    }
    
    // Save all image URLs to a file
    file_put_contents(storage_path('logs/yupoo_image_urls.txt'), implode("\n", $imageUrls));
    
} catch (\Exception $e) {
    log_message("\n=== ERROR ===");
    log_message("Message: " . $e->getMessage());
    log_message("File: " . $e->getFile() . " (" . $e->getLine() . ")");
    log_message("Trace: " . $e->getTraceAsString());
}

log_message("\nTest complete.");
