<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$app->boot();

// Resolve YupooService from the container
$yupooService = $app->make(\App\Services\YupooService::class);

// Test fetching albums
echo "Testing fetchAlbums...\n";
try {
    $albums = $yupooService->fetchAlbums('https://297228164.x.yupoo.com/albums', 1, 1);
    echo "Successfully fetched " . count($albums) . " albums\n";
    
    if (!empty($albums)) {
        echo "First album: " . json_encode($albums[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        
        // Test fetching images for the first album
        echo "\nTesting fetchAlbumImages...\n";
        $images = $yupooService->fetchAlbumImages($albums[0]['url']);
        echo "Successfully fetched " . count($images) . " images\n";
        
        if (!empty($images)) {
            echo "First image: " . json_encode($images[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
