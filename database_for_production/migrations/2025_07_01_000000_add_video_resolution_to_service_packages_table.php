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
        Schema::table('service_packages', function (Blueprint $table) {
            $table->integer('max_video_width')->nullable()->after('storage_limit');
            $table->integer('max_video_height')->nullable()->after('max_video_width');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_packages', function (Blueprint $table) {
            $table->dropColumn(['max_video_width', 'max_video_height']);
        });
    }
}; 