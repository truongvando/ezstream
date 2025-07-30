<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YoutubeChannelSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'youtube_channel_id',
        'subscriber_count',
        'video_count',
        'view_count',
        'comment_count',
        'snapshot_date',
    ];

    protected $casts = [
        'snapshot_date' => 'datetime',
        'subscriber_count' => 'integer',
        'video_count' => 'integer',
        'view_count' => 'integer',
        'comment_count' => 'integer',
    ];

    /**
     * Get the channel that owns this snapshot
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(YoutubeChannel::class, 'youtube_channel_id');
    }

    /**
     * Format subscriber count for display
     */
    public function getFormattedSubscriberCountAttribute(): string
    {
        return $this->formatNumber($this->subscriber_count);
    }

    /**
     * Format view count for display
     */
    public function getFormattedViewCountAttribute(): string
    {
        return $this->formatNumber($this->view_count);
    }

    /**
     * Format video count for display
     */
    public function getFormattedVideoCountAttribute(): string
    {
        return number_format($this->video_count);
    }

    /**
     * Helper to format large numbers
     */
    private function formatNumber(int $number): string
    {
        if ($number >= 1000000000) {
            return round($number / 1000000000, 1) . 'B';
        } elseif ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }

        return number_format($number);
    }
}
