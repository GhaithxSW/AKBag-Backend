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
        // First, add the category_id column as nullable
        Schema::table('images', function (Blueprint $table) {
            $table->foreignId('category_id')
                  ->nullable()
                  ->after('title')
                  ->constrained('categories')
                  ->nullOnDelete();
        });

        // If there were any categories in the string field, we would migrate them here
        // This is a placeholder for future data migration if needed
        
        // Finally, drop the old category string column
        Schema::table('images', function (Blueprint $table) {
            if (Schema::hasColumn('images', 'category')) {
                $table->dropColumn('category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, drop the foreign key constraint
        Schema::table('images', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });
        
        // Then drop the column
        Schema::table('images', function (Blueprint $table) {
            $table->dropColumn('category_id');
        });
        
        // Re-add the old category string column
        Schema::table('images', function (Blueprint $table) {
            $table->string('category')->nullable()->after('title');
        });
    }
};
