<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Drop the foreign key constraint from images table
        if (Schema::hasColumn('images', 'category_id')) {
            Schema::table('images', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });
        }

        // Drop the category_id column from images table
        if (Schema::hasColumn('images', 'category_id')) {
            Schema::table('images', function (Blueprint $table) {
                $table->dropColumn('category_id');
            });
        }

        // Drop the category column from images table if it exists
        if (Schema::hasColumn('images', 'category')) {
            Schema::table('images', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }

        // Drop the categories table if it exists
        if (Schema::hasTable('categories')) {
            Schema::dropIfExists('categories');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Recreate categories table
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Add category_id column to images table
        if (!Schema::hasColumn('images', 'category_id')) {
            Schema::table('images', function (Blueprint $table) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('album_id')
                    ->constrained('categories')
                    ->nullOnDelete();
            });
        }
    }
};
