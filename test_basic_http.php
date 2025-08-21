<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

function test_http($url) {
    echo "Testing URL: $url\n";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n" .
                      "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n" .
                      "Accept-Language: en-US,en;q=0.5\r\n" .
                      "Connection: close\r\n",
            'ignore_errors' => true,
            'timeout' => 30,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    $start = microtime(true);
    $response = @file_get_contents($url, false, $context);
    $end = microtime(true);
    
    if ($response === false) {
        $error = error_get_last();
        echo "Error: " . ($error['message'] ?? 'Unknown error') . "\n";
        return;
    }
    
    $headers = [];
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (strpos($header, 'HTTP/') === 0) {
                $headers['status'] = $header;
            } else {
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
            }
        }
    }
    
    echo "Response Time: " . round(($end - $start) * 1000) . " ms\n";
    echo "Status: " . ($headers['status'] ?? 'N/A') . "\n";
    echo "Content-Type: " . ($headers['content-type'] ?? 'N/A') . "\n";
    echo "Content-Length: " . strlen($response) . " bytes\n";
    
    // Save response to file
    $filename = 'response_' . md5($url) . '.html';
    file_put_contents($filename, $response);
    echo "Response saved to: $filename\n";
    
    return $response;
}

// Test Yupoo URL
$yupooUrl = 'https://297228164.x.yupoo.com';
$response = test_http($yupooUrl);

// If successful, try to extract album links
if ($response) {
    echo "\nChecking for album links...\n";
    
    // Simple regex to find album links
    if (preg_match_all('/<a[^>]+href=[\'\"]([^\'\"]+albums\/[0-9]+)[\'\"]/i', $response, $matches)) {
        $albums = array_unique($matches[1]);
        echo "Found " . count($albums) . " album links\n";
        
        // Test first album if found
        if (!empty($albums[0])) {
            $albumUrl = $albums[0];
            if (strpos($albumUrl, 'http') !== 0) {
                $albumUrl = 'https://297228164.x.yupoo.com' . (strpos($albumUrl, '/') === 0 ? '' : '/') . $albumUrl;
            }
            
            echo "\nTesting album: $albumUrl\n";
            test_http($albumUrl);
        }
    } else {
        echo "No album links found in the response.\n";
        
        // Try to find any JSON data in the response
        if (preg_match('/<script[^>]*>\s*window\.__INITIAL_STATE__\s*=\s*({.+?})\s*<\/script>/is', $response, $jsonMatches)) {
            echo "\nFound JSON data in the response. Saving...\n";
            file_put_contents('yupoo_data.json', $jsonMatches[1]);
            echo "JSON data saved to: yupoo_data.json\n";
        }
    }
}

echo "\nTest complete.\n";
