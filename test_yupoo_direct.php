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

try {
    $yupooUrl = 'https://297228164.x.yupoo.com';
    
    log_message("Testing connection to Yupoo: $yupooUrl");
    
    $client = new Client([
        'verify' => false, // Disable SSL verification for testing
        'timeout' => 30,
        'connect_timeout' => 10,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Connection' => 'keep-alive',
        ],
    ]);
    
    // Test 1: Basic connection
    log_message("Sending GET request to: $yupooUrl");
    $response = $client->get($yupooUrl);
    
    $statusCode = $response->getStatusCode();
    $contentType = $response->getHeaderLine('content-type');
    $body = (string) $response->getBody();
    $bodyLength = strlen($body);
    
    log_message("Response Status: $statusCode");
    log_message("Content-Type: $contentType");
    log_message("Body Length: $bodyLength bytes");
    
    // Save response for inspection
    $debugFile = storage_path('logs/yupoo_debug_response.html');
    file_put_contents($debugFile, $body);
    log_message("Response saved to: $debugFile");
    
    // Test 2: Try to access albums page
    $albumsUrl = rtrim($yupooUrl, '/') . '/albums';
    log_message("\nTesting albums page: $albumsUrl");
    
    $response = $client->get($albumsUrl);
    $albumsStatus = $response->getStatusCode();
    $albumsBody = (string) $response->getBody();
    
    log_message("Albums Page Status: $albumsStatus");
    log_message("Albums Page Length: " . strlen($albumsBody) . " bytes");
    
    // Save albums response
    $albumsFile = storage_path('logs/yupoo_albums_response.html');
    file_put_contents($albumsFile, $albumsBody);
    log_message("Albums response saved to: $albumsFile");
    
    // Prefer a specific album if provided
    $preferredAlbum = '/albums/191167499?uid=1';
    $albumUrlToTest = null;
    if ($preferredAlbum) {
        $albumUrlToTest = rtrim($yupooUrl, '/') . '/' . ltrim($preferredAlbum, '/');
        log_message("Using preferred album URL: $albumUrlToTest");
    }

    // Try to extract album links if no preferred URL
    if (!$albumUrlToTest) {
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+albums\/\d+)["\']/i', $albumsBody, $matches)) {
            $albumLinks = array_unique($matches[1]);
            log_message("\nFound " . count($albumLinks) . " album links");
            
            if (!empty($albumLinks[0])) {
                $albumUrlToTest = $albumLinks[0];
                if (strpos($albumUrlToTest, 'http') !== 0) {
                    $albumUrlToTest = rtrim($yupooUrl, '/') . '/' . ltrim($albumUrlToTest, '/');
                }
            }
        } else {
            log_message("\nNo album links found in the albums page");
        }
    }

    // Test the chosen album URL
    if ($albumUrlToTest) {
        log_message("\nTesting album: $albumUrlToTest");
        try {
            $response = $client->get($albumUrlToTest);
            $albumStatus = $response->getStatusCode();
            $albumBody = (string) $response->getBody();
            
            log_message("Album Page Status: $albumStatus");
            log_message("Album Page Length: " . strlen($albumBody) . " bytes");
            
            // Save album response
            $albumFile = storage_path('logs/yupoo_album_response.html');
            file_put_contents($albumFile, $albumBody);
            log_message("Album response saved to: $albumFile");
            
            // Detect album password modal to skip
            if (stripos($albumBody, 'This album is encrypted') !== false) {
                log_message("Album appears to be password-protected. Skipping image extraction.");
            } else {
                // Try to extract image URLs from HTML (including lazy attrs)
                $imageUrls = [];
                if (preg_match_all('/<img[^>]+(?:src|data-src|data-original)=["\']([^"\']+\.(?:jpg|jpeg|png|gif|webp))[^"\']*["\']/i', $albumBody, $imgMatches)) {
                    foreach ($imgMatches[1] as $u) {
                        // Normalize protocol-relative URLs
                        if (strpos($u, '//') === 0) {
                            $u = 'https:' . $u;
                        } elseif (strpos($u, '/') === 0) {
                            $u = rtrim($yupooUrl, '/') . $u;
                        }
                        $imageUrls[] = $u;
                    }
                    $imageUrls = array_values(array_unique($imageUrls));
                }
                
                if (!empty($imageUrls)) {
                    log_message("\nFound " . count($imageUrls) . " image URLs in album");
                    $count = min(5, count($imageUrls));
                    for ($i = 0; $i < $count; $i++) {
                        log_message(sprintf("  %d. %s", $i + 1, $imageUrls[$i]));
                    }
                    // Save image URLs
                    $imageFile = storage_path('logs/yupoo_album_image_urls.txt');
                    file_put_contents($imageFile, implode("\n", $imageUrls));
                    log_message("Image URLs saved to: $imageFile");
                } else {
                    log_message("\nNo image URLs found in album page via HTML attributes");
                    // Try JSON fallback
                    if (preg_match('/<script[^>]*>\s*window\.__INITIAL_STATE__\s*=\s*({.+?})\s*<\/script>/is', $albumBody, $jsonMatches)) {
                        log_message("Found JSON data in album page");
                        $jsonFile = storage_path('logs/yupoo_album_json.json');
                        file_put_contents($jsonFile, $jsonMatches[1]);
                        log_message("JSON data saved to: $jsonFile");
                    }
                }
            }
        } catch (\Exception $e) {
            log_message("\nError fetching album: " . $e->getMessage());
        }
    }
    
} catch (RequestException $e) {
    log_message("\n=== Request Error ===");
    log_message("Message: " . $e->getMessage());
    if ($e->hasResponse()) {
        log_message("Status: " . $e->getResponse()->getStatusCode());
        log_message("Response: " . $e->getResponse()->getBody()->getContents());
    }
} catch (\Exception $e) {
    log_message("\n=== Error ===");
    log_message("Message: " . $e->getMessage());
    log_message("File: " . $e->getFile() . " (" . $e->getLine() . ")");
    log_message("Trace: " . $e->getTraceAsString());
}

log_message("\nTest complete");
