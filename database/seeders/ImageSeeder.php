<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Image;

class ImageSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1,6) as $albumId) {
            for ($i = 1; $i <= 3; $i++) {
                Image::create([
                    'album_id' => $albumId,
                    'title' => "Image $i for Album $albumId",
                    'category' => 'Sample Category',
                    'image_path' => 'images/sample.jpg',
                ]);
            }
        }
    }
}
