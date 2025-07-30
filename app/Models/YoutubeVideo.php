<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YoutubeVideo extends Model
{
    use HasFactory;

    protected $fillable = [
        'youtube_channel_id',
        'video_id',
        'title',
        'description',
        'thumbnail_url',
        'published_at',
        'duration_seconds',
        'category_id',
        'tags',
        'status',
        'last_checked_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'tags' => 'array',
        'duration_seconds' => 'integer',
    ];

    /**
     * Get the channel that owns this video
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(YoutubeChannel::class, 'youtube_channel_id');
    }

    /**
     * Get all snapshots for this video
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(\App\Models\YoutubeVideoSnapshot::class);
    }

    /**
     * Get the latest snapshot
     */
    public function latestSnapshot()
    {
        // Use loaded snapshots if available, otherwise query
        if ($this->relationLoaded('snapshots') && $this->snapshots->count() > 0) {
            return $this->snapshots->first();
        }

        return $this->snapshots()->latest('snapshot_date')->first();
    }

    /**
     * Get the previous snapshot for comparison
     */
    public function previousSnapshot()
    {
        return $this->snapshots()
            ->orderBy('snapshot_date', 'desc')
            ->skip(1)
            ->first();
    }

    /**
     * Get YouTube URL
     */
    public function getYoutubeUrlAttribute(): string
    {
        return "https://www.youtube.com/watch?v={$this->video_id}";
    }

    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration_seconds) {
            return 'N/A';
        }

        $hours = floor($this->duration_seconds / 3600);
        $minutes = floor(($this->duration_seconds % 3600) / 60);
        $seconds = $this->duration_seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Get performance metrics compared to previous snapshot
     */
    public function getPerformanceMetrics(): array
    {
        $latest = $this->latestSnapshot();
        $previous = $this->previousSnapshot();

        if (!$latest || !$previous) {
            return [
                'view_growth' => 0,
                'like_growth' => 0,
                'comment_growth' => 0,
                'engagement_rate' => 0,
            ];
        }

        $viewGrowth = $latest->view_count - $previous->view_count;
        $likeGrowth = $latest->like_count - $previous->like_count;
        $commentGrowth = $latest->comment_count - $previous->comment_count;

        return [
            'view_growth' => $viewGrowth,
            'like_growth' => $likeGrowth,
            'comment_growth' => $commentGrowth,
            'engagement_rate' => $latest->view_count > 0 
                ? (($latest->like_count + $latest->comment_count) / $latest->view_count) * 100 
                : 0,
        ];
    }

    /**
     * Check if video needs status check
     */
    public function needsStatusCheck(): bool
    {
        if (!$this->last_checked_at) {
            return true;
        }

        // Check dead videos less frequently (weekly)
        if ($this->status === 'dead') {
            return $this->last_checked_at->diffInDays(now()) >= 7;
        }

        // Check live videos daily
        return $this->last_checked_at->diffInHours(now()) >= 24;
    }

    /**
     * Scope for live videos
     */
    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    /**
     * Scope for dead videos
     */
    public function scopeDead($query)
    {
        return $query->where('status', 'dead');
    }

    /**
     * Scope for videos that need status check
     */
    public function scopeNeedsStatusCheck($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_checked_at')
              ->orWhere(function ($subQ) {
                  $subQ->where('status', 'dead')
                       ->where('last_checked_at', '<', now()->subDays(7));
              })
              ->orWhere(function ($subQ) {
                  $subQ->where('status', '!=', 'dead')
                       ->where('last_checked_at', '<', now()->subHours(24));
              });
        });
    }
}
