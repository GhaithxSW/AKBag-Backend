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
        if (!Schema::hasColumn('images', 'original_url')) {
            Schema::table('images', function (Blueprint $table) {
                $table->string('original_url')->nullable()->after('image_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('images', 'original_url')) {
            Schema::table('images', function (Blueprint $table) {
                $table->dropColumn('original_url');
            });
        }
    }
};
