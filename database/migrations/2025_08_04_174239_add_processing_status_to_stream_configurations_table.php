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
            $table->boolean('auto_start_when_ready')->default(false)
                ->comment('Auto-start stream when all files finish processing');
            $table->json('processing_files')->nullable()
                ->comment('Track which files are still processing');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            $table->dropColumn(['auto_start_when_ready', 'processing_files']);
        });
    }
};
