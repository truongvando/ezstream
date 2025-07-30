<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YoutubeAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'youtube_channel_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'triggered_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'triggered_at' => 'datetime',
    ];

    /**
     * Get the user that owns the alert
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the channel that triggered the alert
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(YoutubeChannel::class, 'youtube_channel_id');
    }

    /**
     * Get alert icon based on type
     */
    public function getIconAttribute(): string
    {
        return match($this->type) {
            'new_video' => '🎬',
            'subscriber_milestone' => '🎯',
            'view_milestone' => '👀',
            'growth_spike' => '📈',
            'video_viral' => '🔥',
            'channel_inactive' => '😴',
            default => '📢'
        };
    }

    /**
     * Get alert color based on type
     */
    public function getColorAttribute(): string
    {
        return match($this->type) {
            'new_video' => 'blue',
            'subscriber_milestone' => 'green',
            'view_milestone' => 'purple',
            'growth_spike' => 'emerald',
            'video_viral' => 'red',
            'channel_inactive' => 'gray',
            default => 'blue'
        };
    }

    /**
     * Scope for unread alerts
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for specific channel
     */
    public function scopeForChannel($query, $channelId)
    {
        return $query->where('youtube_channel_id', $channelId);
    }

    /**
     * Mark alert as read
     */
    public function markAsRead(): void
    {
        $this->update(['is_read' => true]);
    }

    /**
     * Create alert for user
     */
    public static function createAlert(
        int $userId,
        int $channelId,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): self {
        return self::create([
            'user_id' => $userId,
            'youtube_channel_id' => $channelId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'triggered_at' => now(),
        ]);
    }
}
