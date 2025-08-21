<?php
// Minimal test script for Yupoo connection

echo "Testing connection to Yupoo...\n";

// Test 1: Check if we can resolve the domain
$host = '297228164.x.yupoo.com';
echo "Resolving $host...\n";
$ip = gethostbyname($host);

echo "IP: $ip\n";

if ($ip === $host) {
    echo "Failed to resolve host. DNS issue detected.\n";
    exit(1);
}

// Test 2: Try to open a socket connection
echo "\nTrying to connect to $ip on port 443...\n";
$fp = @fsockopen("ssl://$ip", 443, $errno, $errstr, 10);

if (!$fp) {
    echo "Failed to connect: $errstr ($errno)\n";
} else {
    echo "Successfully connected to $ip on port 443\n";
    
    // Send a simple HTTP request
    $out = "GET / HTTP/1.1\r\n";
    $out .= "Host: $host\r\n";
    $out .= "User-Agent: TestScript/1.0\r\n";
    $out .= "Connection: close\r\n\r\n";
    
    fwrite($fp, $out);
    
    // Read response
    echo "\nResponse:\n";
    echo "--------\n";
    
    $response = '';
    while (!feof($fp)) {
        $response .= fgets($fp, 128);
        // Stop after reading headers
        if (strpos($response, "\r\n\r\n") !== false) {
            break;
        }
    }
    
    echo $response;
    echo "--------\n";
    
    fclose($fp);
}

echo "\nTest complete.\n";
