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
     * Get platform icon as SVG
     */
    public function getPlatformIconAttribute(): string
    {
        switch ($this->platform) {
            case 'YouTube':
                return '<svg class="w-4 h-4 inline-block text-red-600" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
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
                    <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                </svg>';
            default:
                return '<svg class="w-4 h-4 inline-block text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
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
