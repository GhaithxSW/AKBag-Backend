<?php

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

function log_message($message) {
    echo "[" . date('Y-m-d H:i:s') . "] $message\n";
}

// Test Yupoo connection
log_message("Starting Yupoo connection test...");

// Test 1: Simple file_get_contents
$url = 'https://297228164.x.yupoo.com';
log_message("\nTest 1: file_get_contents to $url");

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
    log_message("Error: " . ($error['message'] ?? 'Unknown error'));
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
    
    log_message("Response Time: " . round(($end - $start) * 1000) . " ms");
    log_message("Status: " . ($headers['status'] ?? 'N/A'));
    log_message("Content-Type: " . ($headers['content-type'] ?? 'N/A'));
    log_message("Content-Length: " . strlen($response) . " bytes");
    
    // Save response to file
    $filename = 'yupoo_response_' . date('Ymd_His') . '.html';
    file_put_contents($filename, $response);
    log_message("Response saved to: $filename");
    
    // Check for common patterns
    log_message("\nChecking response content...");
    
    if (stripos($response, '<html') !== false) {
        log_message("- Contains HTML content");
    }
    
    if (stripos($response, 'yupoo') !== false) {
        log_message("- Contains 'yupoo' in content");
    }
    
    if (preg_match('/<title>(.*?)<\/title>/i', $response, $matches)) {
        log_message("- Page title: " . trim($matches[1]));
    }
    
    // Check for common Yupoo elements
    if (preg_match('/<div[^>]*class=["\'][^"\']*album[^"\']*["\'][^>]*>/i', $response)) {
        log_message("- Found album elements");
    }
    
    if (preg_match('/<img[^>]+src=["\']([^"\']+\.(?:jpg|jpeg|png|gif|webp))[^"\']*["\']/i', $response, $matches)) {
        log_message("- Found image: " . $matches[1]);
    }
}

// Test 2: Check if we can resolve the domain
log_message("\nTest 2: DNS Lookup");
$host = '297228164.x.yupoo.com';
$ip = gethostbyname($host);

if ($ip === $host) {
    log_message("Failed to resolve $host");
} else {
    log_message("Resolved $host to $ip");
    
    // Test 3: Try to connect to the IP directly
    log_message("\nTest 3: Connect to IP directly");
    $fp = @fsockopen($ip, 443, $errno, $errstr, 10);
    
    if (!$fp) {
        log_message("Failed to connect to $ip: $errstr ($errno)");
    } else {
        log_message("Successfully connected to $ip on port 443");
        fclose($fp);
    }
}

log_message("\nTest complete.");
