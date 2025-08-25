<?php

namespace App\Console\Commands;

use App\Services\YupooService;
use Illuminate\Console\Command;

class TestYupooService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yupoo:test-service {--limit=1 : Maximum number of albums to fetch} {--test-import : Test the full import workflow}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the YupooService functionality';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $testImport = $this->option('test-import');
        $yupooService = app(YupooService::class);

        if ($testImport) {
            $this->info("Testing full import workflow with limit: {$limit} albums");

            try {
                $stats = $yupooService->importAlbums('https://297228164.x.yupoo.com/albums', $limit);

                $this->info("\nImport completed successfully!");
                $this->info('Total albums: '.$stats['total_albums']);
                $this->info('Imported albums: '.$stats['imported_albums']);
                $this->info('Skipped albums: '.$stats['skipped_albums']);
                $this->info('Imported images: '.$stats['imported_images']);
                $this->info('Skipped images: '.$stats['skipped_images']);

                if (! empty($stats['errors'])) {
                    $this->warn("\nEncountered ".count($stats['errors']).' errors:');
                    foreach ($stats['errors'] as $error) {
                        $this->error('- '.$error);
                    }
                }
            } catch (\Exception $e) {
                $this->error('Error during import: '.$e->getMessage());
                $this->error('File: '.$e->getFile().':'.$e->getLine());

                return 1;
            }
        } else {
            $this->info("Testing YupooService with limit: {$limit} albums");

            try {
                // Test fetching albums
                $this->info("\n1. Fetching albums...");
                $albums = $yupooService->fetchAlbums('https://297228164.x.yupoo.com/albums', 1, $limit);

                $this->info('Successfully fetched '.count($albums).' albums');

                if (! empty($albums)) {
                    $this->info('First album: '.json_encode($albums[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                    // Test fetching images for the first album
                    $this->info("\n2. Fetching images for the first album...");
                    $images = $yupooService->fetchAlbumImages($albums[0]['url']);

                    $this->info('Successfully fetched '.count($images).' images');

                    if (! empty($images)) {
                        $this->info('First image: '.json_encode($images[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    }
                }

                return 0;
            } catch (\Exception $e) {
                $this->error('Error: '.$e->getMessage());
                $this->error('File: '.$e->getFile().':'.$e->getLine());
                $this->error('Trace: '.$e->getTraceAsString());

                return 1;
            }
        }
    }
}
