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
        Schema::table('user_files', function (Blueprint $table) {
            $table->string('stream_video_id')->nullable()->after('path')->comment('BunnyCDN Stream Library video ID for SRS streaming');
            $table->json('stream_metadata')->nullable()->after('stream_video_id')->comment('Stream Library metadata (HLS URLs, etc.)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_files', function (Blueprint $table) {
            $table->dropColumn(['stream_video_id', 'stream_metadata']);
        });
    }
};
