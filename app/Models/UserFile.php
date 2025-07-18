<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vps_server_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'status',
        'auto_delete_after_stream',
        'scheduled_deletion_at',
        'status_message',
        'source_url',
        'google_drive_file_id',
        'error_message',
        'downloaded_at',
        'download_source',
        'primary_vps_id',
        'storage_locations',
        's3_key',
        's3_etag',
        'public_url',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
        'scheduled_deletion_at' => 'datetime',
        'auto_delete_after_stream' => 'boolean',
        'storage_locations' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vpsServer(): BelongsTo
    {
        return $this->belongsTo(VpsServer::class);
    }
}
