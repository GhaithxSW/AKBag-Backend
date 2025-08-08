<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Collection;

class CollectionSeeder extends Seeder
{
    public function run(): void
    {
        Collection::create([
            'name' => 'Nature',
            'description' => 'Beautiful landscapes and nature scenes.'
        ]);
        Collection::create([
            'name' => 'Urban',
            'description' => 'City life and architecture.'
        ]);
        Collection::create([
            'name' => 'Abstract',
            'description' => 'Abstract art and patterns.'
        ]);
    }
}
