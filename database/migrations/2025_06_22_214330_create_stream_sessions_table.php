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
        Schema::create('stream_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_file_id')->constrained()->onDelete('cascade');
            $table->foreignId('vps_server_id')->constrained()->onDelete('cascade');
            
            // Trạng thái stream
            $table->enum('status', ['initializing', 'active', 'stopped', 'failed', 'error'])->default('initializing');
            
            // Thông tin streaming
            $table->string('streaming_strategy')->nullable(); // 'download_streaming' hoặc 'url_streaming'
            $table->string('stream_pid')->nullable(); // Process ID của FFmpeg
            $table->text('ffmpeg_command')->nullable(); // Command FFmpeg đã chạy
            $table->string('local_file_path')->nullable(); // Đường dẫn file trên VPS (nếu download)
            $table->string('streaming_url')->nullable(); // URL Google Drive (nếu URL streaming)
            
            // Thời gian và lý do
            $table->integer('estimated_setup_time')->nullable(); // Thời gian setup ước tính (giây)
            $table->timestamp('stopped_at')->nullable();
            $table->string('stop_reason')->nullable(); // 'user_stopped', 'error', 'auto_cleanup', etc.
            
            // Kết quả dọn dẹp
            $table->json('cleanup_result')->nullable(); // JSON chứa thông tin dọn dẹp
            
            // Thống kê
            $table->bigInteger('bytes_streamed')->default(0);
            $table->integer('duration_seconds')->default(0);
            $table->integer('viewer_count')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['vps_server_id', 'status']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stream_sessions');
    }
};
