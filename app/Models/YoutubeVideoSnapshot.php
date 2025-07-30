<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YoutubeVideoSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'youtube_video_id',
        'view_count',
        'like_count',
        'comment_count',
        'snapshot_date',
    ];

    protected $casts = [
        'snapshot_date' => 'datetime',
        'view_count' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
    ];

    /**
     * Get the video that owns this snapshot
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(YoutubeVideo::class, 'youtube_video_id');
    }

    /**
     * Format view count for display
     */
    public function getFormattedViewCountAttribute(): string
    {
        return $this->formatNumber($this->view_count);
    }

    /**
     * Format like count for display
     */
    public function getFormattedLikeCountAttribute(): string
    {
        return $this->formatNumber($this->like_count);
    }

    /**
     * Format comment count for display
     */
    public function getFormattedCommentCountAttribute(): string
    {
        return $this->formatNumber($this->comment_count);
    }

    /**
     * Get engagement rate
     */
    public function getEngagementRateAttribute(): float
    {
        if ($this->view_count == 0) {
            return 0;
        }

        return (($this->like_count + $this->comment_count) / $this->view_count) * 100;
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
