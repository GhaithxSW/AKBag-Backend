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
        Schema::table('collections', function (Blueprint $table) {
            // Add index on name for search and sorting
            $table->index('name', 'idx_collections_name');

            // Add index on created_at for sorting
            $table->index('created_at', 'idx_collections_created_at');
        });

        Schema::table('featured_images', function (Blueprint $table) {
            // Add index on is_active for filtering active images
            $table->index('is_active', 'idx_featured_images_active');

            // Add composite index on is_active and position for ordering
            $table->index(['is_active', 'position'], 'idx_featured_images_active_position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex('idx_collections_name');
            $table->dropIndex('idx_collections_created_at');
        });

        Schema::table('featured_images', function (Blueprint $table) {
            $table->dropIndex('idx_featured_images_active');
            $table->dropIndex('idx_featured_images_active_position');
        });
    }
};
