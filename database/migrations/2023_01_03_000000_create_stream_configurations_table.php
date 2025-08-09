<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stream_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('INACTIVE');
            $table->string('status_message')->nullable();
            $table->json('video_source_path');
            $table->json('streaming_destinations');
            $table->string('streaming_method')->default('ffmpeg');
            $table->json('streaming_settings')->nullable();
            $table->foreignId('user_file_id')->nullable()->constrained('user_files')->onDelete('set null');
            $table->string('thumbnail_path')->nullable();
            $table->boolean('loop_video')->default(false);
            $table->boolean('auto_start')->default(false);
            $table->timestamp('scheduled_start_time')->nullable();
            $table->timestamp('scheduled_end_time')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('stream_configurations');
    }
};
