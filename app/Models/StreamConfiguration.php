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
     * Get platform icon
     */
    public function getPlatformIconAttribute(): string
    {
        switch ($this->platform) {
            case 'YouTube':
                return '📺';
            case 'Facebook':
                return '📘';
            case 'Twitch':
                return '🎮';
            case 'TikTok':
                return '🎵';
            default:
                return '⚙️';
        }
    }
}
