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
        'stream_key',
        'ffmpeg_options',
        'status',
        'ffmpeg_pid',
        'output_log_path',
        'last_started_at',
        'last_stopped_at',
    ];

    protected $casts = [
        'last_started_at' => 'datetime',
        'last_stopped_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vpsServer()
    {
        return $this->belongsTo(VpsServer::class);
    }
}
