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
        'is_locked',
        'upload_session_url',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
        'scheduled_deletion_at' => 'datetime',
        'auto_delete_after_stream' => 'boolean',
        'is_locked' => 'boolean',
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

    /**
     * Get the CDN URL for the file
     */
    public function getCdnUrlAttribute(): string
    {
        if ($this->disk === 'bunny_cdn') {
            $cdnUrl = config('bunnycdn.cdn_url');
            if (empty($cdnUrl)) {
                // Fallback to default BunnyCDN URL format
                $storageZone = config('bunnycdn.storage_zone', 'ezstream');
                $cdnUrl = "https://{$storageZone}.b-cdn.net";
            }
            return rtrim($cdnUrl, '/') . '/' . ltrim($this->path, '/');
        }

        return $this->path;
    }

    /**
     * Get the filename from path or original_name
     */
    public function getFilenameAttribute(): string
    {
        if (!empty($this->original_name)) {
            return $this->original_name;
        }

        return basename($this->path);
    }
}
