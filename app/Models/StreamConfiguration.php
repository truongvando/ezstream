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
        'ffmpeg_pid',
        'output_log_path',
        'last_started_at',
        'last_stopped_at',
        'last_status_update',
        'stream_preset',
        'loop',
        'scheduled_at',
        'playlist_order',
        'user_file_id',
    ];

    protected $casts = [
        'last_started_at' => 'datetime',
        'last_stopped_at' => 'datetime',
        'last_status_update' => 'datetime',
        'scheduled_at' => 'datetime',
        'loop' => 'boolean',
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
}
