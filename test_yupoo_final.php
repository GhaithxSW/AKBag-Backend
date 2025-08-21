<?php

// Simple test script to verify Yupoo connection

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to make HTTP requests
function fetch_url($url) {
    $ch = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        CURLOPT_HEADER => true,
        CURLOPT_ENCODING => 'gzip, deflate, br',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Cache-Control: max-age=0',
        ],
    ];
    
    curl_setopt_array($ch, $options);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    
    curl_close($ch);
    
    return [
        'response' => $response,
        'error' => $error,
        'info' => $info,
    ];
}

// Test URL
$url = 'https://297228164.x.yupoo.com';
echo "Testing connection to: $url\n";

$result = fetch_url($url);

if ($result['error']) {
    echo "cURL Error: " . $result['error'] . "\n";
} else {
    echo "Status: " . $result['info']['http_code'] . "\n";
    echo "Content Type: " . ($result['info']['content_type'] ?? 'N/A') . "\n";
    echo "Downloaded: " . $result['info']['size_download'] . " bytes\n";
    echo "Total Time: " . $result['info']['total_time'] . "s\n";
    
    // Save response to file
    $filename = 'yupoo_final_response.html';
    file_put_contents($filename, $result['response']);
    echo "Response saved to: $filename\n";
    
    // Show first 500 characters of response
    echo "\n=== Response Preview ===\n";
    echo substr($result['response'], 0, 500) . "\n...\n";
    
    // Check for common patterns
    $checks = [
        'Contains HTML' => strpos($result['response'], '<!DOCTYPE') !== false || strpos($result['response'], '<html') !== false,
        'Contains Yupoo' => stripos($result['response'], 'yupoo') !== false,
        'Contains album' => stripos($result['response'], 'album') !== false,
        'Contains photo' => stripos($result['response'], 'photo') !== false,
    ];
    
    echo "\n=== Content Analysis ===\n";
    foreach ($checks as $label => $result) {
        echo "- $label: " . ($result ? 'Yes' : 'No') . "\n";
    }
    
    // Try to extract title
    if (preg_match('/<title[^>]*>(.*?)<\/title>/i', $result['response'], $matches)) {
        echo "\nPage Title: " . trim($matches[1]) . "\n";
    }
    
    // Try to extract image URLs
    if (preg_match_all('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|gif|webp))[^"\']*["\']/i', $result['response'], $matches)) {
        $imageUrls = array_unique($matches[1]);
        echo "\nFound " . count($imageUrls) . " image URLs in the page.\n";
        
        // Save image URLs
        file_put_contents('yupoo_image_urls.txt', implode("\n", $imageUrls));
        echo "Image URLs saved to: yupoo_image_urls.txt\n";
        
        // Show first 3 image URLs
        echo "\nFirst 3 image URLs:\n";
        $count = min(3, count($imageUrls));
        for ($i = 0; $i < $count; $i++) {
            echo ($i + 1) . ". " . $imageUrls[$i] . "\n";
        }
    }
    
    // Try to extract album links
    if (preg_match_all('/<a[^>]+href=["\']([^"\']+albums\/\d+)["\']/i', $result['response'], $matches)) {
        $albumLinks = array_unique($matches[1]);
        echo "\nFound " . count($albumLinks) . " album links in the page.\n";
        
        // Save album links
        file_put_contents('yupoo_album_links.txt', implode("\n", $albumLinks));
        echo "Album links saved to: yupoo_album_links.txt\n";
        
        // Show first 3 album links
        echo "\nFirst 3 album links:\n";
        $count = min(3, count($albumLinks));
        for ($i = 0; $i < $count; $i++) {
            echo ($i + 1) . ". " . $albumLinks[$i] . "\n";
        }
    }
}

echo "\nTest complete.\n";
