<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\YupooService;

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simple test for YupooService
try {
    echo "=== Testing YupooService ===\n";
    
    $yupoo = new YupooService();
    
    // Test fetching albums
    echo "\n=== Fetching Albums ===\n";
    $albums = $yupoo->fetchAlbums('https://297228164.x.yupoo.com');
    
    if (empty($albums)) {
        echo "No albums found.\n";
    } else {
        echo "Found " . count($albums) . " albums.\n";
        
        // Display first few albums
        $count = min(3, count($albums));
        for ($i = 0; $i < $count; $i++) {
            $album = $albums[$i];
            echo sprintf("%d. %s (%s)\n", $i + 1, $album['name'] ?? 'No name', $album['url'] ?? 'No URL');
        }
        
        // Test fetching images from first album
        if (!empty($albums[0]['url'])) {
            echo "\n=== Testing Image Fetch for First Album ===\n";
            $images = $yupoo->fetchAlbumImages($albums[0]['url']);
            
            if (empty($images)) {
                echo "No images found in the first album.\n";
            } else {
                echo "Found " . count($images) . " images in the first album.\n";
                
                // Display first few images
                $count = min(3, count($images));
                for ($i = 0; $i < $count; $i++) {
                    $image = $images[$i];
                    echo sprintf("%d. %s\n", $i + 1, $image['url'] ?? 'No URL');
                }
            }
        }
    }
    
} catch (\Exception $e) {
    echo "\n=== ERROR ===\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " (" . $e->getLine() . ")\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
