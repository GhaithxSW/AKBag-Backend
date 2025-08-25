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
        Schema::table('albums', function (Blueprint $table) {
            // Check if slug column exists before trying to drop it
            if (Schema::hasColumn('albums', 'slug')) {
                $table->dropColumn('slug');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            // Only add slug column if it doesn't exist
            if (! Schema::hasColumn('albums', 'slug')) {
                $table->string('slug')->unique()->after('description');
            }
        });
    }
};
