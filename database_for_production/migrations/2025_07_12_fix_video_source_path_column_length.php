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
        Schema::table('stream_configurations', function (Blueprint $table) {
            // Change video_source_path from varchar(255) to LONGTEXT to support large JSON data
            $table->longText('video_source_path')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            // Revert back to varchar(255) - WARNING: This may cause data loss if content is too long
            $table->string('video_source_path', 255)->change();
        });
    }
};
