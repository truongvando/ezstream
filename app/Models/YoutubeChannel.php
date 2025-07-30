<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YoutubeChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'channel_id',
        'channel_name',
        'channel_url',
        'channel_handle',
        'description',
        'thumbnail_url',
        'country',
        'channel_created_at',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'channel_created_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the channel
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all snapshots for this channel
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(\App\Models\YoutubeChannelSnapshot::class);
    }

    /**
     * Get all videos for this channel
     */
    public function videos(): HasMany
    {
        return $this->hasMany(\App\Models\YoutubeVideo::class);
    }

    /**
     * Get all alerts for this channel
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(\App\Models\YoutubeAlert::class);
    }

    /**
     * Get alert settings for this channel
     */
    public function alertSettings(): HasMany
    {
        return $this->hasMany(\App\Models\YoutubeAlertSetting::class);
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
     * Get growth metrics compared to previous snapshot
     */
    public function getGrowthMetrics(): array
    {
        // Use loaded snapshots if available, otherwise query
        if ($this->relationLoaded('snapshots') && $this->snapshots->count() >= 2) {
            $latest = $this->snapshots->first();
            $previous = $this->snapshots->skip(1)->first();
        } else {
            $latest = $this->latestSnapshot();
            $previous = $this->previousSnapshot();
        }

        if (!$latest || !$previous) {
            return [
                'subscriber_growth' => 0,
                'video_growth' => 0,
                'view_growth' => 0,
                'growth_rate' => 0,
            ];
        }

        return [
            'subscriber_growth' => $latest->subscriber_count - $previous->subscriber_count,
            'video_growth' => $latest->video_count - $previous->video_count,
            'view_growth' => $latest->view_count - $previous->view_count,
            'growth_rate' => $previous->subscriber_count > 0
                ? (($latest->subscriber_count - $previous->subscriber_count) / $previous->subscriber_count) * 100
                : 0,
        ];
    }

    /**
     * Check if channel needs sync (daily)
     */
    public function needsSync(): bool
    {
        if (!$this->last_synced_at) {
            return true;
        }

        return $this->last_synced_at->diffInHours(now()) >= 24;
    }

    /**
     * Get active videos count
     */
    public function getActiveVideosCountAttribute(): int
    {
        return $this->videos()->where('status', 'live')->count();
    }

    /**
     * Scope for active channels
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for channels that need sync
     */
    public function scopeNeedsSync($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_synced_at')
              ->orWhere('last_synced_at', '<', now()->subHours(24));
        });
    }

    /**
     * Scope for user's channels (non-admin)
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
