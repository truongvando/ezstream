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
        Schema::create('stream_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vps_server_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_source_path'); // đường dẫn file/stream trên VPS
            $table->string('rtmp_url');
            $table->string('stream_key');
            $table->text('ffmpeg_options')->nullable(); // các tùy chọn ffmpeg bổ sung
            $table->enum('status', ['PENDING', 'ACTIVE', 'INACTIVE', 'ERROR', 'STARTING', 'STOPPING'])->default('PENDING');
            $table->integer('ffmpeg_pid')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_stopped_at')->nullable();
            $table->string('output_log_path')->nullable(); // đường dẫn log ffmpeg trên VPS
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stream_configurations');
    }
};
