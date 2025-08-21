<?php

// Simple script to test Yupoo connection

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

function fetch_url($url) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        CURLOPT_HEADER => true,
        CURLOPT_VERBOSE => true,
    ]);
    
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
    $filename = 'yupoo_response_' . date('Ymd_His') . '.html';
    file_put_contents($filename, $result['response']);
    echo "Response saved to: $filename\n";
    
    // Show first 500 characters of response
    echo "\n=== Response Preview ===\n";
    echo substr($result['response'], 0, 500) . "\n...\n";
}

echo "\nTest complete.\n";
