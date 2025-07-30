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
        // YouTube Channels table
        Schema::create('youtube_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('channel_id')->unique(); // YouTube channel ID
            $table->string('channel_name');
            $table->string('channel_url');
            $table->string('channel_handle')->nullable(); // @username
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('country')->nullable();
            $table->timestamp('channel_created_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
            $table->index('last_synced_at');
        });

        // Channel Snapshots table (historical data)
        Schema::create('youtube_channel_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('youtube_channel_id')->constrained()->onDelete('cascade');
            $table->bigInteger('subscriber_count')->default(0);
            $table->bigInteger('video_count')->default(0);
            $table->bigInteger('view_count')->default(0);
            $table->bigInteger('comment_count')->default(0);
            $table->timestamp('snapshot_date');
            $table->timestamps();
            
            $table->index(['youtube_channel_id', 'snapshot_date']);
            $table->unique(['youtube_channel_id', 'snapshot_date'], 'yt_channel_snapshot_unique');
        });

        // YouTube Videos table
        Schema::create('youtube_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('youtube_channel_id')->constrained()->onDelete('cascade');
            $table->string('video_id')->unique(); // YouTube video ID
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->timestamp('published_at');
            $table->integer('duration_seconds')->nullable();
            $table->string('category_id')->nullable();
            $table->json('tags')->nullable();
            $table->enum('status', ['live', 'dead', 'private', 'unlisted'])->default('live');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            
            $table->index(['youtube_channel_id', 'published_at']);
            $table->index(['status', 'last_checked_at']);
        });

        // Video Snapshots table (track metrics changes)
        Schema::create('youtube_video_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('youtube_video_id')->constrained()->onDelete('cascade');
            $table->bigInteger('view_count')->default(0);
            $table->bigInteger('like_count')->default(0);
            $table->bigInteger('comment_count')->default(0);
            $table->timestamp('snapshot_date');
            $table->timestamps();
            
            $table->index(['youtube_video_id', 'snapshot_date']);
            $table->unique(['youtube_video_id', 'snapshot_date'], 'yt_video_snapshot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_video_snapshots');
        Schema::dropIfExists('youtube_videos');
        Schema::dropIfExists('youtube_channel_snapshots');
        Schema::dropIfExists('youtube_channels');
    }
};
