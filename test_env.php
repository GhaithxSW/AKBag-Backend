<?php
// Simple environment test script

// Test basic PHP functionality
echo "=== PHP Environment Test ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "OS: " . PHP_OS . "\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') . "\n";

// Test file operations
$testFile = 'test_file.txt';
$testContent = 'This is a test file created at ' . date('Y-m-d H:i:s');

// Write to file
$writeResult = file_put_contents($testFile, $testContent);
if ($writeResult === false) {
    echo "Error: Could not write to file\n";
} else {
    echo "Successfully wrote $writeResult bytes to $testFile\n";
}

// Read from file
$readContent = @file_get_contents($testFile);
if ($readContent === false) {
    echo "Error: Could not read from file\n";
} else {
    echo "Successfully read from $testFile\n";
    echo "File content: " . substr($readContent, 0, 100) . "...\n";
}

// Test directory operations
$testDir = 'test_dir';
if (!file_exists($testDir)) {
    if (mkdir($testDir)) {
        echo "Successfully created directory: $testDir\n";
    } else {
        echo "Error: Could not create directory: $testDir\n";
    }
}

// Test network functions
function test_network() {
    echo "\n=== Network Test ===\n";
    
    // Test DNS lookup
    $host = 'www.google.com';
    $ip = gethostbyname($host);
    echo "DNS Lookup for $host: $ip\n";
    
    // Test HTTP request
    $url = 'http://example.com';
    echo "Testing HTTP request to $url...\n";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $error = error_get_last();
        echo "Error: " . ($error['message'] ?? 'Unknown error') . "\n";
    } else {
        echo "Successfully received " . strlen($response) . " bytes\n";
        echo "Response starts with: " . substr($response, 0, 100) . "...\n";
    }
}

// Run network tests if enabled
$enableNetworkTests = true;
if ($enableNetworkTests) {
    test_network();
}

echo "\nTest complete.\n";
