<?php
// Simple HTTP test for Yupoo

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// URL to test
$url = 'https://297228164.x.yupoo.com';
echo "Testing connection to: $url\n";

// Set up stream context
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n" .
                  "Accept: text/html\r\n" .
                  "Connection: close\r\n",
        'timeout' => 30,
        'ignore_errors' => true,
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

// Make the request
echo "Sending request...\n";
$start = microtime(true);
$response = @file_get_contents($url, false, $context);
$end = microtime(true);

if ($response === false) {
    $error = error_get_last();
    echo "Error: " . ($error['message'] ?? 'Unknown error') . "\n";
} else {
    echo "Request completed in " . round(($end - $start) * 1000) . " ms\n";
    
    // Get response headers
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
    
    echo "Status: " . ($headers['status'] ?? 'N/A') . "\n";
    echo "Content-Type: " . ($headers['content-type'] ?? 'N/A') . "\n";
    echo "Content-Length: " . strlen($response) . " bytes\n";
    
    // Save response to file
    $filename = 'yupoo_simple_response.html';
    file_put_contents($filename, $response);
    echo "Response saved to: $filename\n";
    
    // Display first 500 characters of response
    echo "\n=== Response Preview ===\n";
    echo substr($response, 0, 500) . "\n...\n";
}

echo "\nTest complete.\n";
