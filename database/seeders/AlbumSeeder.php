<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Album;

class AlbumSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([1,2,3] as $collectionId) {
            Album::create([
                'collection_id' => $collectionId,
                'title' => 'First Album for Collection ' . $collectionId,
                'description' => 'Description for first album.',
                'cover_image' => null,
                'slug' => 'first-album-' . $collectionId,
            ]);
            Album::create([
                'collection_id' => $collectionId,
                'title' => 'Second Album for Collection ' . $collectionId,
                'description' => 'Description for second album.',
                'cover_image' => null,
                'slug' => 'second-album-' . $collectionId,
            ]);
        }
    }
}
