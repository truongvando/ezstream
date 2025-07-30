<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YoutubeAlertSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'youtube_channel_id',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /**
     * Get the user that owns the settings
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the channel these settings apply to
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(YoutubeChannel::class, 'youtube_channel_id');
    }

    /**
     * Get default alert settings
     */
    public static function getDefaultSettings(): array
    {
        return [
            'new_video' => [
                'enabled' => true,
                'threshold' => 1, // Alert for every new video
            ],
            'subscriber_milestone' => [
                'enabled' => true,
                'thresholds' => [1000, 5000, 10000, 50000, 100000, 500000, 1000000], // Milestone values
            ],
            'view_milestone' => [
                'enabled' => true,
                'thresholds' => [10000, 50000, 100000, 500000, 1000000, 5000000, 10000000], // View milestones
            ],
            'growth_spike' => [
                'enabled' => true,
                'threshold' => 10, // Alert if growth > 10% in one day
            ],
            'video_viral' => [
                'enabled' => true,
                'view_threshold' => 100000, // Views in 24h to be considered viral
                'growth_threshold' => 1000, // % growth to be considered viral
            ],
            'channel_inactive' => [
                'enabled' => true,
                'threshold' => 30, // Days without new video
            ],
        ];
    }

    /**
     * Get settings for a specific alert type
     */
    public function getSettingForType(string $type): array
    {
        return $this->settings[$type] ?? self::getDefaultSettings()[$type] ?? [];
    }

    /**
     * Check if alert type is enabled
     */
    public function isAlertEnabled(string $type): bool
    {
        $setting = $this->getSettingForType($type);
        return $setting['enabled'] ?? false;
    }

    /**
     * Update settings for a specific alert type
     */
    public function updateAlertSetting(string $type, array $settings): void
    {
        $currentSettings = $this->settings ?? self::getDefaultSettings();
        $currentSettings[$type] = array_merge($currentSettings[$type] ?? [], $settings);
        
        $this->update(['settings' => $currentSettings]);
    }

    /**
     * Get or create settings for user and channel
     */
    public static function getForUserAndChannel(int $userId, int $channelId): self
    {
        return self::firstOrCreate(
            [
                'user_id' => $userId,
                'youtube_channel_id' => $channelId,
            ],
            [
                'settings' => self::getDefaultSettings(),
            ]
        );
    }
}
