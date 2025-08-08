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
        // Drop the unique index first if it exists
        Schema::table('categories', function (Blueprint $table) {
            // Drop the unique index
            $table->dropUnique('categories_slug_unique');
            // Then drop the column
            $table->dropColumn('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->after('name');
            $table->unique('slug', 'categories_slug_unique');
        });
    }
};
