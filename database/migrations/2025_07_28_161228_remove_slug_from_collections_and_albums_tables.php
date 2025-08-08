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
        // Remove slug from collections table
        Schema::table('collections', function (Blueprint $table) {
            // Drop the unique index first
            $table->dropUnique('collections_slug_unique');
            // Then drop the column
            $table->dropColumn('slug');
        });

        // Remove slug from albums table
        Schema::table('albums', function (Blueprint $table) {
            // Drop the unique index first
            $table->dropUnique('albums_slug_unique');
            // Then drop the column
            $table->dropColumn('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections_and_albums_tables', function (Blueprint $table) {
            //
        });
    }
};
