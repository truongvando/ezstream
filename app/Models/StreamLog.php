<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StreamLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'stream_id',
        'event',
        'level',
        'category',
        'context',
        'user_id',
        'vps_id',
        'session_id'
    ];

    protected $casts = [
        'context' => 'array'
    ];

    /**
     * Get the stream that owns the log
     */
    public function stream()
    {
        return $this->belongsTo(StreamConfiguration::class, 'stream_id');
    }

    /**
     * Get the user that owns the log
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the VPS server that owns the log
     */
    public function vpsServer()
    {
        return $this->belongsTo(VpsServer::class, 'vps_id');
    }

    /**
     * Scope for specific stream
     */
    public function scopeForStream($query, $streamId)
    {
        return $query->where('stream_id', $streamId);
    }

    /**
     * Scope for specific level
     */
    public function scopeLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope for specific category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for recent logs
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for errors
     */
    public function scopeErrors($query)
    {
        return $query->whereIn('level', ['ERROR', 'CRITICAL']);
    }
}
