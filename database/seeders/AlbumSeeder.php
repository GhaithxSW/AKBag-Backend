<?php

namespace Database\Seeders;

use App\Models\Album;
use Illuminate\Database\Seeder;

class AlbumSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([1, 2, 3] as $collectionId) {
            Album::create([
                'collection_id' => $collectionId,
                'title' => 'First Album for Collection '.$collectionId,
                'description' => 'Description for first album.',
                'cover_image' => null,
            ]);
            Album::create([
                'collection_id' => $collectionId,
                'title' => 'Second Album for Collection '.$collectionId,
                'description' => 'Description for second album.',
                'cover_image' => null,
            ]);
        }
    }
}
