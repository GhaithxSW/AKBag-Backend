<?php

namespace App\Console\Commands;

use App\Services\YupooService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestYupooImporter extends Command
{
    protected $signature = 'yupoo:test-importer {--limit=5 : Maximum number of albums to import} {--debug : Enable debug output}';

    protected $description = 'Test the Yupoo importer functionality';

    protected $yupooService;

    protected $debug = false;

    public function __construct(YupooService $yupooService)
    {
        parent::__construct();
        $this->yupooService = $yupooService;
    }

    public function handle()
    {
        $this->debug = $this->option('debug');
        $limit = (int) $this->option('limit');

        $this->info('=== Yupoo Importer Test ===');
        $this->line("Testing with limit: {$limit} albums");

        try {
            // Test fetching albums
            $this->info('\n1. Fetching albums from Yupoo...');
            $albums = $this->yupooService->fetchAlbums('https://297228164.x.yupoo.com/albums', 1, $limit);

            if (! is_array($albums) || empty($albums)) {
                $this->error('❌ No albums found or failed to fetch albums');

                return 1;
            }

            $this->info(sprintf('✅ Successfully fetched %d albums', count($albums)));

            // Test fetching images for each album
            foreach ($albums as $index => $album) {
                $albumNum = $index + 1;
                $this->info("\n{$albumNum}. Processing album: ".($album['title'] ?? 'Untitled'));

                if (empty($album['url'])) {
                    $this->warn('   ⚠️  Album has no URL, skipping');

                    continue;
                }

                // Test fetching images for this album
                $this->testAlbumImages($album['url']);

                // Only process the first album if not in debug mode
                if (! $this->debug && $albumNum >= 1) {
                    $this->info('\nSkipping remaining albums (use --debug to process all)');
                    break;
                }
            }

            $this->info('\n✅ Yupoo importer test completed successfully!');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());
            $this->error('File: '.$e->getFile().':'.$e->getLine());

            if ($this->debug) {
                $this->error('Stack trace: '.$e->getTraceAsString());
            }

            Log::error('Yupoo importer test failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    protected function testAlbumImages($albumUrl)
    {
        $this->info('   Fetching images...');

        try {
            $images = $this->yupooService->fetchAlbumImages($albumUrl);

            if (! is_array($images) || empty($images)) {
                $this->warn('   ⚠️  No images found in this album');

                return;
            }

            $this->info(sprintf('   ✅ Found %d images', count($images)));

            // Test downloading the first image if in debug mode
            if ($this->debug && ! empty($images[0]['url'])) {
                $this->testImageDownload($images[0]['url']);
            }

        } catch (\Exception $e) {
            $this->error('   ❌ Failed to fetch images: '.$e->getMessage());
            if ($this->debug) {
                $this->error('   '.$e->getTraceAsString());
            }
            throw $e;
        }
    }

    protected function testImageDownload($imageUrl)
    {
        $this->info('   Testing image download...');

        try {
            $path = $this->yupooService->downloadImage($imageUrl, 'test-download');

            if ($path) {
                $fullPath = storage_path('app/public/'.ltrim($path, '/'));
                $size = file_exists($fullPath) ? filesize($fullPath) : 0;
                $this->info(sprintf('   ✅ Downloaded: %s (%.2f KB)',
                    basename($path), $size / 1024));
            } else {
                $this->warn('   ⚠️  Image download returned empty path');
            }

        } catch (\Exception $e) {
            $this->error('   ❌ Download failed: '.$e->getMessage());
            if ($this->debug) {
                $this->error('   '.$e->getTraceAsString());
            }
            throw $e;
        }
    }
}
