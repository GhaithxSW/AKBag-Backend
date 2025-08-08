<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;

class ListCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all categories in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $categories = Category::withCount('images')->get();
        
        if ($categories->isEmpty()) {
            $this->info('No categories found in the database.');
            return 0;
        }
        
        $this->info('Categories in the database:');
        $this->table(
            ['ID', 'Name', 'Description', 'Image Count', 'Created At', 'Updated At'],
            $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description ? substr($category->description, 0, 50) . '...' : 'N/A',
                    'images_count' => $category->images_count,
                    'created_at' => $category->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $category->updated_at->format('Y-m-d H:i:s'),
                ];
            })
        );
        
        return 0;
    }
}
