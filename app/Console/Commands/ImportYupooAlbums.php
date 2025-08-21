<?php

namespace App\Console\Commands;

use App\Services\YupooService;
use Illuminate\Console\Command;

class ImportYupooAlbums extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yupoo:import 
                            {--url= : Yupoo albums URL (default: https://297228164.x.yupoo.com/albums)}
                            {--limit=0 : Maximum number of albums to import (0 for no limit)}
                            {--debug : Enable debug output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import albums and images from Yupoo';

    /**
     * The Yupoo service instance.
     *
     * @var \App\Services\YupooService
     */
    protected $yupooService;

    /**
     * Create a new command instance.
     *
     * @param  \App\Services\YupooService  $yupooService
     * @return void
     */
    public function __construct(YupooService $yupooService)
    {
        parent::__construct();
        $this->yupooService = $yupooService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $baseUrl = $this->option('url') ?? 'https://297228164.x.yupoo.com/albums';
        $limit = (int)$this->option('limit');
        
        // Set debug mode if requested
        if ($this->option('debug')) {
            config(['app.debug' => true]);
            // Also enable YupooService detailed debug logging
            config(['yupoo.logging.debug' => true]);
            $this->info("Debug mode enabled (Yupoo debug logging ON)");
        }

        // Ensure we have console output
        if (!app()->bound('console.output')) {
            app()->instance('console.output', $this->output);
        }

        $this->info("=== Yupoo Import ===");
        $this->info("URL: {$baseUrl}");
        $this->info("Max albums: " . ($limit > 0 ? $limit : 'No limit'));
        $this->info("Debug mode: " . ($this->option('debug') ? 'Enabled' : 'Disabled'));
        
        try {
            // Run the import
            $stats = $this->yupooService->importAlbums($baseUrl, $limit);
            
            // Display results
            $this->info("\n=== Import Summary ===");
            $this->info("Total albums processed: " . $stats['total_albums']);
            $this->info("Albums imported: " . $stats['imported_albums']);
            $this->info("Albums skipped: " . $stats['skipped_albums']);
            $this->info("Images imported: " . $stats['imported_images']);
            $this->info("Images skipped: " . $stats['skipped_images']);
            
            if (!empty($stats['errors'])) {
                $this->error("\n=== Errors ===");
                foreach ($stats['errors'] as $error) {
                    $this->error("- " . $error);
                }
                $this->error("\nSome errors occurred during import. Check the logs for details.");
                return 1;
            }
            
            $this->info("\nImport completed successfully!");
            return 0;
            
        } catch (\Exception $e) {
            $this->error("\nFatal error during import: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }
}
