<?php
// Simple PHP test script

echo "=== PHP Test Script ===\n";

// Test basic PHP functionality
echo "PHP Version: " . phpversion() . "\n";
echo "OS: " . PHP_OS . "\n";

// Test file operations
$testFile = 'test_php_output.txt';
$testContent = 'This is a test file created at ' . date('Y-m-d H:i:s') . "\n";

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
    echo "File content: " . $readContent;
}

// Test JSON functionality
$testData = ['test' => 'value', 'number' => 42];
$jsonData = json_encode($testData);
if ($jsonData === false) {
    echo "Error: JSON encode failed: " . json_last_error_msg() . "\n";
} else {
    echo "JSON test successful: $jsonData\n";
}

echo "\nTest complete.\n";
