<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

function log_message($message, $data = null) {
    $output = "[" . date('Y-m-d H:i:s') . "] $message";
    if ($data !== null) {
        if (is_array($data) || is_object($data)) {
            $output .= "\n" . json_encode($data, JSON_PRETTY_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            $output .= " $data";
        }
    }
    echo $output . "\n";
}

function inspect_response($response) {
    log_message("\n=== Response Inspection ===");
    log_message("Status:", $response->status());
    
    $headers = [];
    foreach ($response->headers() as $name => $values) {
        $headers[$name] = $response->header($name);
    }
    log_message("Headers:", $headers);
    
    $body = $response->body();
    log_message("Body length: " . strlen($body) . " bytes");
    
    // Check for common patterns in the response
    $checks = [
        'Contains HTML' => strpos($body, '<!DOCTYPE') !== false || strpos($body, '<html') !== false,
        'Contains Yupoo' => stripos($body, 'yupoo') !== false,
        'Contains JSON' => strpos($body, '{') !== false && strpos($body, '}') !== false,
        'Contains album' => stripos($body, 'album') !== false,
        'Contains photo' => stripos($body, 'photo') !== false,
    ];
    
    log_message("Content Analysis:", $checks);
    
    // Try to extract title if HTML
    if (preg_match('/<title[^>]*>(.*?)<\/title>/i', $body, $matches)) {
        log_message("Page title: " . trim($matches[1]));
    }
    
    // Try to extract JSON data if present
    if (preg_match('/<script[^>]*>\s*window\.__INITIAL_STATE__\s*=\s*({.+?})\s*<\/script>/is', $body, $matches)) {
        $jsonData = json_decode($matches[1], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            log_message("Found JSON data in response");
            
            // Save the JSON data for inspection
            $jsonFile = storage_path('logs/yupoo_initial_state.json');
            file_put_contents($jsonFile, json_encode($jsonData, JSON_PRETTY_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            log_message("JSON data saved to: $jsonFile");
            
            // Check for album data in JSON
            if (isset($jsonData['albumList']['list'])) {
                log_message("Found " . count($jsonData['albumList']['list']) . " albums in JSON data");
            }
        }
    }
    
    // Try to extract image URLs
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|gif|webp))[^"\']*["\']/i', $body, $matches)) {
        $imageUrls = array_unique($matches[1]);
        log_message("Found " . count($imageUrls) . " unique image URLs in HTML");
        
        // Save image URLs
        $imageFile = storage_path('logs/yupoo_image_urls.txt');
        file_put_contents($imageFile, implode("\n", $imageUrls));
        log_message("Image URLs saved to: $imageFile");
    }
    
    // Try to extract album links
    if (preg_match_all('/<a[^>]+href=["\']([^"\']+albums\/\d+)["\']/i', $body, $matches)) {
        $albumLinks = array_unique($matches[1]);
        log_message("Found " . count($albumLinks) . " album links in HTML");
        
        // Save album links
        $albumFile = storage_path('logs/yupoo_album_links.txt');
        file_put_contents($albumFile, implode("\n", $albumLinks));
        log_message("Album links saved to: $albumFile");
    }
    
    echo "\n";
}

try {
    $url = 'https://297228164.x.yupoo.com';
    log_message("Testing connection to: $url");
    
    // Make the request
    $response = Http::withOptions([
        'verify' => false, // Disable SSL verification for testing
        'timeout' => 30,
        'connect_timeout' => 10,
        'debug' => true,
    ])->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language' => 'en-US,en;q=0.5',
        'Accept-Encoding' => 'gzip, deflate, br',
        'Connection' => 'keep-alive',
        'Upgrade-Insecure-Requests' => '1',
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
        'Sec-Fetch-Site' => 'none',
        'Sec-Fetch-User' => '?1',
        'Cache-Control' => 'max-age=0',
    ])->get($url);
    
    // Save full response to file
    $filename = storage_path('logs/yupoo_laravel_response.html');
    file_put_contents($filename, $response->body());
    log_message("Full response saved to: $filename");
    
    // Inspect the response in detail
    inspect_response($response);
    
} catch (\Exception $e) {
    log_message("\n=== ERROR ===");
    log_message("Message: " . $e->getMessage());
    log_message("File: " . $e->getFile() . " (" . $e->getLine() . ")");
    log_message("Trace: " . $e->getTraceAsString());
}

log_message("\nTest complete.");
