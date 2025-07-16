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
        'playlist_order',
        'user_file_id',
        'keep_files_after_stop',
    ];

    protected $casts = [
        'last_started_at' => 'datetime',
        'last_stopped_at' => 'datetime',
        'last_status_update' => 'datetime',
        'scheduled_at' => 'datetime',
        'loop' => 'boolean',
        'keep_files_after_stop' => 'boolean',
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
