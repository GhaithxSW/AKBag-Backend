<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure the categories table exists
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        } else {
            // If the table exists, make sure it doesn't have a slug column
            if (Schema::hasColumn('categories', 'slug')) {
                Schema::table('categories', function (Blueprint $table) {
                    // Drop the unique index first if it exists
                    $sm = Schema::getConnection()->getDoctrineSchemaManager();
                    $indexes = $sm->listTableIndexes('categories');
                    
                    if (isset($indexes['categories_slug_unique'])) {
                        $table->dropUnique('categories_slug_unique');
                    }
                    
                    // Then drop the column
                    $table->dropColumn('slug');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a one-way migration
    }
};
