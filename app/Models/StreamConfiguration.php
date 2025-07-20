<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StreamConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vps_server_id',
        'title',
        'description',
        'video_source_path',
        'rtmp_url',
        'rtmp_backup_url',
        'stream_key',
        'ffmpeg_options',
        'status',
        'progress_percentage',
        'progress_message',
        'progress_stage',
        'ffmpeg_pid',
        'output_log_path',
        'last_started_at',
        'last_stopped_at',
        'last_status_update',
        'loop',
        'scheduled_at',
        'scheduled_end',
        'enable_schedule',
        'playlist_order',
        'user_file_id',
        'keep_files_on_agent',
        'is_quick_stream',
        'auto_delete_from_cdn',
        'error_message',
        'last_user_action_at',
        'last_user_action',
        'sync_notes',
    ];

    protected $casts = [
        'last_started_at' => 'datetime',
        'last_stopped_at' => 'datetime',
        'last_status_update' => 'datetime',
        'scheduled_at' => 'datetime',
        'scheduled_end' => 'datetime',
        'loop' => 'boolean',
        'enable_schedule' => 'boolean',
        'keep_files_on_agent' => 'boolean',
        'is_quick_stream' => 'boolean',
        'auto_delete_from_cdn' => 'boolean',
        'video_source_path' => 'array', // Cast JSON to array automatically
        'push_urls' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vpsServer()
    {
        return $this->belongsTo(VpsServer::class);
    }

    public function userFile()
    {
        return $this->belongsTo(UserFile::class);
    }

    /**
     * Boot method to add model event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // Log when stream is being deleted
        static::deleting(function ($stream) {
            \Log::warning("🗑️ [CRITICAL] Stream #{$stream->id} is being deleted", [
                'title' => $stream->title,
                'status' => $stream->status,
                'user_id' => $stream->user_id,
                'vps_server_id' => $stream->vps_server_id,
                'is_quick_stream' => $stream->is_quick_stream,
                'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)
            ]);
        });
    }

    /**
     * Get platform name from RTMP URL
     */
    public function getPlatformAttribute(): string
    {
        if (!$this->rtmp_url) {
            return 'Custom';
        }

        if (str_contains($this->rtmp_url, 'youtube.com')) {
            return 'YouTube';
        }

        if (str_contains($this->rtmp_url, 'facebook.com')) {
            return 'Facebook';
        }

        if (str_contains($this->rtmp_url, 'twitch.tv')) {
            return 'Twitch';
        }

        if (str_contains($this->rtmp_url, 'tiktok.com')) {
            return 'TikTok';
        }

        return 'Custom';
    }

    /**
     * Get platform icon as SVG (simplified to avoid morph errors)
     */
    public function getPlatformIconAttribute(): string
    {
        switch ($this->platform) {
            case 'YouTube':
                return '<svg class="w-4 h-4 inline-block text-red-600" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                </svg>';
            case 'Facebook':
                return '<svg class="w-4 h-4 inline-block text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>';
            case 'Twitch':
                return '<svg class="w-4 h-4 inline-block text-purple-600" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714Z"/>
                </svg>';
            case 'TikTok':
                return '<svg class="w-4 h-4 inline-block text-black dark:text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>
                </svg>';
            default:
                return '<svg class="w-4 h-4 inline-block text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>';
        }
    }

    /**
     * Check if recent user action should take precedence over VPS heartbeat
     */
    public function hasRecentUserAction(int $withinSeconds = 120): bool
    {
        if (!$this->last_user_action_at) {
            return false;
        }

        return now()->diffInSeconds($this->last_user_action_at) < $withinSeconds;
    }

    /**
     * Check if there's a sync conflict between user intent and VPS status
     */
    public function hasSyncConflict(): bool
    {
        // If user recently stopped but status is STREAMING, there's conflict
        if ($this->hasRecentUserAction() &&
            $this->last_user_action === 'stop' &&
            $this->status === 'STREAMING') {
            return true;
        }

        // If user recently started but status is ERROR/INACTIVE, there's conflict
        if ($this->hasRecentUserAction() &&
            $this->last_user_action === 'start' &&
            in_array($this->status, ['ERROR', 'INACTIVE'])) {
            return true;
        }

        return false;
    }

    /**
     * Get sync status for UI display
     */
    public function getSyncStatusAttribute(): string
    {
        if ($this->hasSyncConflict()) {
            return 'conflict';
        }

        if ($this->hasRecentUserAction()) {
            return 'user_action';
        }

        return 'synced';
    }
}
