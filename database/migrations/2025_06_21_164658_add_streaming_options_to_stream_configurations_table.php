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
            $table->string('stream_preset')->default('direct')->after('ffmpeg_options')->comment('Preset for ffmpeg options');
            $table->boolean('loop')->default(false)->after('stream_preset')->comment('Loop the video');
            $table->timestamp('scheduled_at')->nullable()->after('loop')->comment('Scheduled start time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stream_configurations', function (Blueprint $table) {
            $table->dropColumn(['stream_preset', 'loop', 'scheduled_at']);
        });
    }
};
