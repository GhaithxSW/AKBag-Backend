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
            // Drop slug column if it exists
            if (Schema::hasColumn('albums', 'slug')) {
                // Drop unique index first if it exists
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('albums');

                if (isset($indexes['albums_slug_unique'])) {
                    $table->dropUnique('albums_slug_unique');
                }

                $table->dropColumn('slug');
            }

            // Drop cover_image column if it exists
            if (Schema::hasColumn('albums', 'cover_image')) {
                $table->dropColumn('cover_image');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            //
        });
    }
};
