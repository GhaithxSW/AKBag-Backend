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
        $limit = (int) $this->option('limit');

        // Set debug mode if requested
        if ($this->option('debug')) {
            config(['app.debug' => true]);
            // Also enable YupooService detailed debug logging
            config(['yupoo.logging.debug' => true]);
            $this->info('Debug mode enabled (Yupoo debug logging ON)');
        }

        // Ensure we have console output
        if (! app()->bound('console.output')) {
            app()->instance('console.output', $this->output);
        }

        // Display performance settings
        $batchSize = config('yupoo.import.batch_size', 8);
        $concurrentDownloads = config('yupoo.import.concurrent_downloads', 5);
        $requestDelay = config('yupoo.import.request_delay', 1);
        $imageDelay = config('yupoo.import.image_download_delay', 100000) / 1000; // Convert to ms

        $this->info('=== Yupoo Import (Optimized) ===');
        $this->info("URL: {$baseUrl}");
        $this->info('Max albums: '.($limit > 0 ? $limit : 'No limit'));
        $this->info('Debug mode: '.($this->option('debug') ? 'Enabled' : 'Disabled'));
        $this->info('Performance settings:');
        $this->info("  - Concurrent downloads: {$concurrentDownloads}");
        $this->info("  - Batch size: {$batchSize}");
        $this->info("  - Request delay: {$requestDelay}s");
        $this->info("  - Image delay: {$imageDelay}ms");
        $this->info('  - Duplicate check: '.(config('yupoo.import.skip_duplicate_check', false) ? 'Disabled' : 'Enabled'));

        $startTime = microtime(true);

        try {
            // Run the import with enhanced progress tracking
            $stats = $this->yupooService->importAlbumsWithProgress($baseUrl, $limit, function ($type, $current, $total, $message = '') {
                $this->showProgress($type, $current, $total, $message);
            });

            $totalTime = round(microtime(true) - $startTime, 2);

            // Display results with performance metrics
            $this->info("\n=== Import Summary ===");
            $this->info("Total time: {$totalTime}s");
            $this->info('Total albums processed: '.$stats['total_albums']);
            $this->info('Albums imported: '.$stats['imported_albums']);
            $this->info('Albums skipped: '.$stats['skipped_albums']);
            $this->info('Images imported: '.$stats['imported_images']);
            $this->info('Images skipped: '.$stats['skipped_images']);

            if ($stats['imported_images'] > 0 && $totalTime > 0) {
                $imagesPerSecond = round($stats['imported_images'] / $totalTime, 2);
                $this->info("Performance: {$imagesPerSecond} images/second");
            }

            if (! empty($stats['errors'])) {
                $this->error("\n=== Errors (".count($stats['errors']).') ===');
                $maxErrors = 10; // Limit displayed errors
                $displayedErrors = array_slice($stats['errors'], 0, $maxErrors);

                foreach ($displayedErrors as $error) {
                    $this->error('- '.$error);
                }

                if (count($stats['errors']) > $maxErrors) {
                    $remaining = count($stats['errors']) - $maxErrors;
                    $this->error("... and {$remaining} more errors (check logs for details)");
                }

                $this->error("\nSome errors occurred during import. Check the logs for details.");

                return 1;
            }

            $this->info("\n✅ Import completed successfully!");

            return 0;

        } catch (\Exception $e) {
            $this->error("\n❌ Fatal error during import: ".$e->getMessage());
            if ($this->option('debug')) {
                $this->error('Stack trace: '.$e->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Show progress for different operations
     */
    protected function showProgress($type, $current, $total, $message = '')
    {
        $percentage = $total > 0 ? round(($current / $total) * 100) : 0;
        $progress = str_repeat('█', intval($percentage / 5)).str_repeat('░', 20 - intval($percentage / 5));

        switch ($type) {
            case 'albums':
                $this->line("\r📁 Albums: [{$progress}] {$current}/{$total} ({$percentage}%) {$message}", false);
                break;
            case 'images':
                $this->line("\r🖼️  Images: [{$progress}] {$current}/{$total} ({$percentage}%) {$message}", false);
                break;
            case 'download':
                $this->line("\r⬇️  Download: [{$progress}] {$current}/{$total} ({$percentage}%) {$message}", false);
                break;
            default:
                $this->line("\r⚡ Progress: [{$progress}] {$current}/{$total} ({$percentage}%) {$message}", false);
                break;
        }

        if ($current >= $total) {
            $this->info(''); // New line when completed
        }
    }
}
