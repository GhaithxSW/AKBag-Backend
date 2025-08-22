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
        Schema::table('images', function (Blueprint $table) {
            // Add index on original_url for fast duplicate detection
            $table->index('original_url', 'idx_images_original_url');
            
            // Add composite index on album_id and created_at for faster album queries
            $table->index(['album_id', 'created_at'], 'idx_images_album_created');
            
            // Add index on created_at for date-based queries
            $table->index('created_at', 'idx_images_created_at');
        });
        
        Schema::table('albums', function (Blueprint $table) {
            // Add index on created_at for faster album listing
            $table->index('created_at', 'idx_albums_created_at');
            
            // Add index on title for search functionality
            $table->index('title', 'idx_albums_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex('idx_images_original_url');
            $table->dropIndex('idx_images_album_created');
            $table->dropIndex('idx_images_created_at');
        });
        
        Schema::table('albums', function (Blueprint $table) {
            $table->dropIndex('idx_albums_created_at');
            $table->dropIndex('idx_albums_title');
        });
    }
};
