<?php

namespace App\Console\Commands;

use App\Models\Album;
use Illuminate\Console\Command;

class ListAlbums extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'albums:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all albums in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $albums = Album::withCount('images')->get();

        if ($albums->isEmpty()) {
            $this->info('No albums found in the database.');

            return 0;
        }

        $this->info('Albums in the database:');
        $this->table(
            ['ID', 'Title', 'Description', 'Image Count', 'Created At', 'Updated At'],
            $albums->map(function ($album) {
                return [
                    'id' => $album->id,
                    'title' => $album->title,
                    'description' => $album->description ? substr($album->description, 0, 50).'...' : 'N/A',
                    'images_count' => $album->images_count,
                    'created_at' => $album->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $album->updated_at->format('Y-m-d H:i:s'),
                ];
            })
        );

        return 0;
    }
}
