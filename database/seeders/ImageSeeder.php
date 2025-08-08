<?php

namespace Database\Seeders;

use App\Models\Image;
use Illuminate\Database\Seeder;

class ImageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 6) as $albumId) {
            for ($i = 1; $i <= 3; $i++) {
                Image::create([
                    'album_id' => $albumId,
                    'title' => "Image $i for Album $albumId",
                    'image_path' => 'images/sample.jpg',
                ]);
            }
        }
    }
}
