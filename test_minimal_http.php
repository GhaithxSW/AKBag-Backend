<?php
// Minimal HTTP test script

// Disable SSL verification for testing
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

$url = 'https://297228164.x.yupoo.com';
echo "Testing connection to: $url\n";

$start = microtime(true);
$response = @file_get_contents($url, false, $context);
$end = microtime(true);

if ($response === false) {
    $error = error_get_last();
    echo "Error: " . ($error['message'] ?? 'Unknown error') . "\n";
} else {
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
    $filename = 'yupoo_minimal_response_' . date('Ymd_His') . '.html';
    file_put_contents($filename, $response);
    echo "Response saved to: $filename\n";
    
    // Show first 500 characters of response
    echo "\n=== Response Preview ===\n";
    echo substr($response, 0, 500) . "\n...\n";
}

echo "\nTest complete.\n";
