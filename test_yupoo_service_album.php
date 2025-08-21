<?php
// Bootstrap Laravel to use the YupooService from the container
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var \App\Services\YupooService $service */
$service = app(\App\Services\YupooService::class);

$albumUrl = 'https://297228164.x.yupoo.com/albums/191167499?uid=1';

echo "Fetching images from album: {$albumUrl}\n";

try {
    $images = $service->fetchAlbumImages($albumUrl);
    $count = is_array($images) ? count($images) : 0;
    echo "Found {$count} images via YupooService::fetchAlbumImages()\n";

    if ($count > 0) {
        $lines = [];
        foreach ($images as $idx => $img) {
            $url = is_array($img) && isset($img['url']) ? $img['url'] : (string)$img;
            $title = is_array($img) && isset($img['title']) ? $img['title'] : '';
            $lines[] = $url;
            if ($idx < 10) {
                echo sprintf("%3d. %s %s\n", $idx + 1, $url, $title ? ("- " . $title) : '');
            }
        }
        $outPath = __DIR__ . '/storage/logs/yupoo_service_album_urls.txt';
        file_put_contents($outPath, implode(PHP_EOL, $lines));
        echo "Saved URLs to: {$outPath}\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
