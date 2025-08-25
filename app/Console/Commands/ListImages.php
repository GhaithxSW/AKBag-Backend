<?php

namespace App\Console\Commands;

use App\Models\Image;
use Illuminate\Console\Command;

class ListImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all images in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $images = Image::with('album')->get();

        if ($images->isEmpty()) {
            $this->info('No images found in the database.');

            return 0;
        }

        $this->info('Images in the database:');
        $this->table(
            ['ID', 'Title', 'Album', 'URL', 'Created At'],
            $images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'title' => $image->title,
                    'album' => $image->album ? $image->album->title : 'N/A',
                    'url' => $image->url ? substr($image->url, 0, 30).'...' : 'N/A',
                    'created_at' => $image->created_at->format('Y-m-d H:i:s'),
                ];
            })
        );

        return 0;
    }
}
