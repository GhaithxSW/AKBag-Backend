<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YupooService;
use Illuminate\Support\Facades\DB;

class DebugYupooImport extends Command
{
    protected $signature = 'yupoo:debug-import {--limit=1 : Maximum number of albums to process}';
    protected $description = 'Debug the Yupoo import process';

    protected $yupooService;

    public function __construct(YupooService $yupooService)
    {
        parent::__construct();
        $this->yupooService = $yupooService;
    }

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $this->info("Starting Yupoo import debug with limit: " . ($limit > 0 ? $limit : 'no limit'));

        try {
            // Step 1: Check database state before import
            $this->info("\n[1/4] Checking database state before import...");
            $this->checkDatabaseState();
            
            // Step 2: Fetch albums from Yupoo
            $this->info("\n[2/4] Fetching albums from Yupoo...");
            $albums = $this->yupooService->fetchAlbums(null, 1, $limit);
            
            if (empty($albums)) {
                $this->error("No albums found on Yupoo!");
                return 1;
            }

            $this->info(sprintf("Found %d albums on Yupoo", count($albums)));
            
            // Display sample album data
            $this->info("\nSample album data:");
            $sample = $albums[0];
            $this->line("- Title: " . ($sample['title'] ?? 'N/A'));
            $this->line("- URL: " . ($sample['url'] ?? 'N/A'));
            $this->line("- Cover: " . ($sample['cover'] ?? 'N/A'));
            
            // Step 3: Try to import one album
            $this->info("\n[3/4] Testing import of first album...");
            
            $albumData = $albums[0];
            $this->info("Importing album: " . ($albumData['title'] ?? 'Unknown'));
            
            // Create a mock importAlbums method call
            $imported = $this->yupooService->importAlbums(null, 1);
            
            $this->info("\nImport results:");
            $this->line("- Total albums processed: " . ($imported['total_albums'] ?? 0));
            $this->line("- Imported albums: " . ($imported['imported_albums'] ?? 0));
            $this->line("- Skipped albums: " . ($imported['skipped_albums'] ?? 0));
            $this->line("- Imported images: " . ($imported['imported_images'] ?? 0));
            $this->line("- Skipped images: " . ($imported['skipped_images'] ?? 0));
            
            if (!empty($imported['errors'])) {
                $this->error("\nErrors during import:");
                foreach ($imported['errors'] as $error) {
                    $this->line("- " . $error);
                }
            }
            
            // Step 4: Check database state after import
            $this->info("\n[4/4] Checking database state after import...");
            $this->checkDatabaseState();
            
            $this->info("\nDebug completed!");
            
        } catch (\Exception $e) {
            $this->error("Error during debug: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Check the current state of the database
     */
    protected function checkDatabaseState()
    {
        // Check albums
        $dbAlbums = DB::table('albums')->get();
        $this->info(sprintf("Found %d albums in database", $dbAlbums->count()));
        
        if ($dbAlbums->isNotEmpty()) {
            $this->info("\nSample database albums:");
            foreach ($dbAlbums->take(3) as $i => $album) {
                $this->line(sprintf("  [%d] ID: %d, Title: %s, Created: %s", 
                    $i + 1, 
                    $album->id, 
                    $album->title,
                    $album->created_at
                ));
            }
            
            // Check images for the first album
            $firstAlbum = $dbAlbums->first();
            $images = DB::table('images')->where('album_id', $firstAlbum->id)->get();
            $this->info(sprintf("\nFound %d images for album ID %d (%s)", 
                $images->count(), 
                $firstAlbum->id, 
                $firstAlbum->title
            ));
            
            if ($images->isNotEmpty()) {
                $this->info("Sample images:");
                foreach ($images->take(3) as $i => $image) {
                    $this->line(sprintf("  [%d] ID: %d, Title: %s, Path: %s", 
                        $i + 1,
                        $image->id,
                        $image->title,
                        $image->path
                    ));
                }
            }
        }
        
        // Check categories
        $categories = DB::table('categories')->get();
        $this->info(sprintf("\nFound %d categories in database", $categories->count()));
        
        if ($categories->isNotEmpty()) {
            $this->info("Sample categories:");
            foreach ($categories->take(3) as $i => $category) {
                $this->line(sprintf("  [%d] ID: %d, Name: %s, Created: %s", 
                    $i + 1,
                    $category->id,
                    $category->name,
                    $category->created_at
                ));
            }
        }
    }
}
