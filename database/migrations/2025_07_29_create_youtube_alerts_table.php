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
        Schema::create('youtube_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('youtube_channel_id')->constrained()->onDelete('cascade');
            $table->enum('type', [
                'new_video',
                'subscriber_milestone', 
                'view_milestone',
                'growth_spike',
                'video_viral',
                'channel_inactive'
            ]);
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // Store additional data
            $table->boolean('is_read')->default(false);
            $table->timestamp('triggered_at');
            $table->timestamps();
            
            $table->index(['user_id', 'is_read']);
            $table->index(['youtube_channel_id', 'type']);
            $table->index('triggered_at');
        });

        // Alert Settings table
        Schema::create('youtube_alert_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('youtube_channel_id')->constrained()->onDelete('cascade');
            $table->json('settings'); // Store alert preferences
            $table->timestamps();
            
            $table->unique(['user_id', 'youtube_channel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('youtube_alert_settings');
        Schema::dropIfExists('youtube_alerts');
    }
};
