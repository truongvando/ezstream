<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // YouTube Channels
        if (!Schema::hasTable('youtube_channels')) {
            Schema::create('youtube_channels', function (Blueprint $table) {
                $table->id();
                $table->string('channel_id')->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('thumbnail_url')->nullable();
                $table->bigInteger('subscriber_count')->default(0);
                $table->bigInteger('video_count')->default(0);
                $table->bigInteger('view_count')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // YouTube Videos
        if (!Schema::hasTable('youtube_videos')) {
            Schema::create('youtube_videos', function (Blueprint $table) {
                $table->id();
                $table->string('video_id')->unique();
                $table->string('channel_id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('thumbnail_url')->nullable();
                $table->integer('duration')->nullable();
                $table->bigInteger('view_count')->default(0);
                $table->bigInteger('like_count')->default(0);
                $table->bigInteger('comment_count')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                
                $table->index('channel_id');
                $table->index('published_at');
            });
        }

        // YouTube Video Snapshots
        if (!Schema::hasTable('youtube_video_snapshots')) {
            Schema::create('youtube_video_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('video_id');
                $table->bigInteger('view_count');
                $table->bigInteger('like_count');
                $table->bigInteger('comment_count');
                $table->timestamp('snapshot_at');
                $table->timestamps();
                
                $table->index(['video_id', 'snapshot_at']);
            });
        }

        // YouTube Channel Snapshots
        if (!Schema::hasTable('youtube_channel_snapshots')) {
            Schema::create('youtube_channel_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('channel_id');
                $table->bigInteger('subscriber_count');
                $table->bigInteger('video_count');
                $table->bigInteger('view_count');
                $table->timestamp('snapshot_at');
                $table->timestamps();
                
                $table->index(['channel_id', 'snapshot_at']);
            });
        }

        // YouTube Alerts
        if (!Schema::hasTable('youtube_alerts')) {
            Schema::create('youtube_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('type'); // 'new_video', 'subscriber_milestone', etc.
                $table->string('channel_id')->nullable();
                $table->string('video_id')->nullable();
                $table->string('title');
                $table->text('message');
                $table->json('data')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamps();
                
                $table->index(['user_id', 'is_read']);
                $table->index('type');
            });
        }

        // YouTube Alert Settings
        if (!Schema::hasTable('youtube_alert_settings')) {
            Schema::create('youtube_alert_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->json('channels')->nullable(); // Array of channel IDs to monitor
                $table->json('alert_types')->nullable(); // Array of alert types enabled
                $table->boolean('email_notifications')->default(false);
                $table->boolean('push_notifications')->default(true);
                $table->timestamps();
                
                $table->unique('user_id');
            });
        }

        // YouTube AI Analysis
        if (!Schema::hasTable('youtube_ai_analysis')) {
            Schema::create('youtube_ai_analysis', function (Blueprint $table) {
                $table->id();
                $table->string('video_id');
                $table->string('analysis_type'); // 'content', 'performance', 'trends'
                $table->json('analysis_data');
                $table->decimal('confidence_score', 5, 2)->nullable();
                $table->text('summary')->nullable();
                $table->timestamps();
                
                $table->index(['video_id', 'analysis_type']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('youtube_ai_analysis');
        Schema::dropIfExists('youtube_alert_settings');
        Schema::dropIfExists('youtube_alerts');
        Schema::dropIfExists('youtube_channel_snapshots');
        Schema::dropIfExists('youtube_video_snapshots');
        Schema::dropIfExists('youtube_videos');
        Schema::dropIfExists('youtube_channels');
    }
};
