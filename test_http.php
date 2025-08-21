<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

function test_http_request($url) {
    echo "Testing URL: $url\n";
    
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
        CURLOPT_STDERR => $verbose = fopen('php://temp', 'w+'),
    ]);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    
    echo "\n=== cURL Info ===\n";
    echo "Status: " . $info['http_code'] . "\n";
    echo "Content Type: " . ($info['content_type'] ?? 'N/A') . "\n";
    echo "Total Time: " . $info['total_time'] . "s\n";
    echo "Connect Time: " . $info['connect_time'] . "s\n";
    echo "Name Lookup Time: " . $info['namelookup_time'] . "s\n";
    
    if ($error) {
        echo "\n=== cURL Error ===\n$error\n";
    }
    
    echo "\n=== Response Headers ===\n";
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    echo $headers . "\n";
    
    $body = substr($response, $headerSize);
    $bodyFile = 'response_body_' . md5($url) . '.html';
    file_put_contents($bodyFile, $body);
    echo "\nResponse body saved to: $bodyFile\n";
    
    curl_close($ch);
    
    return $response;
}

// Test Yupoo URL
$yupooUrl = 'https://297228164.x.yupoo.com';
test_http_request($yupooUrl);

// Test Yupoo albums URL
$albumsUrl = 'https://297228164.x.yupoo.com/albums';
test_http_request($albumsUrl);

// Test a specific album URL (if available)
$albumUrl = 'https://297228164.x.yupoo.com/albums/12345678'; // This is just an example, replace with actual album ID if known
// test_http_request($albumUrl);

echo "\nTest complete. Check the generated response files for details.\n";
