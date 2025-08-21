<?php
// Simple socket test for Yupoo

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Yupoo details
$host = '297228164.x.yupoo.com';
$port = 443;

// Create a TCP/IP socket
$socket = @fsockopen('ssl://' . $host, $port, $errno, $errstr, 30);

if (!$socket) {
    die("Error: $errstr ($errno)\n");
}

echo "Connected to $host:$port\n";

// Prepare HTTP request
$request = "GET / HTTP/1.1\r\n";
$request .= "Host: $host\r\n";
$request .= "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n";
$request .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n";
$request .= "Connection: close\r\n\r\n";

// Send request
echo "Sending request...\n";
fwrite($socket, $request);

// Read response
$response = '';
while (!feof($socket)) {
    $response .= fgets($socket, 1024);
}

// Close socket
fclose($socket);

// Save response to file
$filename = 'yupoo_socket_response.html';
file_put_contents($filename, $response);
echo "Response saved to: $filename\n";

// Display response info
$headers = [];
$body = '';
$parts = explode("\r\n\r\n", $response, 2);

if (count($parts) === 2) {
    $headerText = $parts[0];
    $body = $parts[1];
    
    // Parse headers
    $headerLines = explode("\r\n", $headerText);
    $headers['status'] = $headerLines[0];
    
    for ($i = 1; $i < count($headerLines); $i++) {
        $parts = explode(':', $headerLines[$i], 2);
        if (count($parts) === 2) {
            $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
    }
}

echo "\n=== Response Headers ===\n";
foreach ($headers as $name => $value) {
    echo "$name: $value\n";
}

echo "\n=== Response Body (first 500 chars) ===\n";
echo substr($body, 0, 500) . "\n...\n";

echo "\nTest complete.\n";
