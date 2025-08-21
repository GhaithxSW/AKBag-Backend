<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\YupooService;
use Illuminate\Support\Facades\Log;

try {
    echo "Testing Yupoo service...\n";
    
    $yupoo = new YupooService();
    
    // Test fetching albums
    echo "Fetching albums...\n";
    $albums = $yupoo->fetchAlbums();
    
    if (empty($albums)) {
        echo "No albums found.\n";
    } else {
        echo "Found " . count($albums) . " albums.\n";
        
        // Test fetching images from first album
        $firstAlbum = reset($albums);
        echo "\nFetching images from album: " . ($firstAlbum['name'] ?? 'Unknown') . "\n";
        
        $images = $yupoo->fetchAlbumImages($firstAlbum['url'] ?? '');
        
        if (empty($images)) {
            echo "No images found in the first album.\n";
        } else {
            echo "Found " . count($images) . " images in the first album.\n";
            
            // Test downloading first image
            $firstImage = reset($images);
            echo "\nTesting image download: " . ($firstImage['url'] ?? 'Unknown') . "\n";
            
            $downloadedPath = $yupoo->downloadImage($firstImage['url'] ?? '');
            
            if ($downloadedPath) {
                echo "Image downloaded successfully to: $downloadedPath\n";
                
                // Clean up
                if (file_exists(storage_path('app/public/' . $downloadedPath))) {
                    unlink(storage_path('app/public/' . $downloadedPath));
                }
            } else {
                echo "Failed to download image.\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    Log::error('Yupoo test error: ' . $e->getMessage());
    Log::error($e->getTraceAsString());
}
