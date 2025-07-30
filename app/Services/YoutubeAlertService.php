<?php

namespace App\Services;

use App\Models\YoutubeAlert;
use App\Models\YoutubeAlertSetting;
use App\Models\YoutubeChannel;
use App\Models\YoutubeChannelSnapshot;
use App\Models\YoutubeVideo;
use Illuminate\Support\Facades\Log;

class YoutubeAlertService
{
    /**
     * Check for new video alerts
     */
    public function checkNewVideoAlerts(YoutubeChannel $channel): void
    {
        // Get videos added in the last 24 hours
        $newVideos = $channel->videos()
            ->where('created_at', '>=', now()->subDay())
            ->get();

        if ($newVideos->isEmpty()) {
            return;
        }

        $settings = YoutubeAlertSetting::getForUserAndChannel($channel->user_id, $channel->id);
        
        if (!$settings->isAlertEnabled('new_video')) {
            return;
        }

        foreach ($newVideos as $video) {
            YoutubeAlert::createAlert(
                $channel->user_id,
                $channel->id,
                'new_video',
                "Video mới từ {$channel->channel_name}",
                "Kênh {$channel->channel_name} vừa đăng video mới: \"{$video->title}\"",
                [
                    'video_id' => $video->video_id,
                    'video_title' => $video->title,
                    'video_url' => $video->youtube_url,
                ]
            );
        }

        Log::info("Created {$newVideos->count()} new video alerts for channel: {$channel->channel_name}");
    }

    /**
     * Check for subscriber milestone alerts
     */
    public function checkSubscriberMilestones(YoutubeChannel $channel): void
    {
        $latest = $channel->latestSnapshot;
        $previous = $channel->previousSnapshot;

        if (!$latest || !$previous) {
            return;
        }

        $settings = YoutubeAlertSetting::getForUserAndChannel($channel->user_id, $channel->id);
        
        if (!$settings->isAlertEnabled('subscriber_milestone')) {
            return;
        }

        $milestones = $settings->getSettingForType('subscriber_milestone')['thresholds'] ?? [];
        
        foreach ($milestones as $milestone) {
            if ($previous->subscriber_count < $milestone && $latest->subscriber_count >= $milestone) {
                YoutubeAlert::createAlert(
                    $channel->user_id,
                    $channel->id,
                    'subscriber_milestone',
                    "🎯 Milestone đạt được!",
                    "Kênh {$channel->channel_name} đã đạt {$this->formatNumber($milestone)} subscribers!",
                    [
                        'milestone' => $milestone,
                        'current_subscribers' => $latest->subscriber_count,
                        'previous_subscribers' => $previous->subscriber_count,
                    ]
                );

                Log::info("Subscriber milestone alert created", [
                    'channel' => $channel->channel_name,
                    'milestone' => $milestone,
                    'current' => $latest->subscriber_count
                ]);
            }
        }
    }

    /**
     * Check for growth spike alerts
     */
    public function checkGrowthSpikes(YoutubeChannel $channel): void
    {
        $latest = $channel->latestSnapshot;
        $previous = $channel->previousSnapshot;

        if (!$latest || !$previous || $previous->subscriber_count == 0) {
            return;
        }

        $settings = YoutubeAlertSetting::getForUserAndChannel($channel->user_id, $channel->id);
        
        if (!$settings->isAlertEnabled('growth_spike')) {
            return;
        }

        $growthRate = (($latest->subscriber_count - $previous->subscriber_count) / $previous->subscriber_count) * 100;
        $threshold = $settings->getSettingForType('growth_spike')['threshold'] ?? 10;

        if ($growthRate >= $threshold) {
            YoutubeAlert::createAlert(
                $channel->user_id,
                $channel->id,
                'growth_spike',
                "📈 Tăng trưởng đột biến!",
                "Kênh {$channel->channel_name} tăng {$this->formatNumber($latest->subscriber_count - $previous->subscriber_count)} subscribers ({$this->formatNumber($growthRate, 2)}%) trong 24h!",
                [
                    'growth_rate' => $growthRate,
                    'subscriber_growth' => $latest->subscriber_count - $previous->subscriber_count,
                    'current_subscribers' => $latest->subscriber_count,
                    'previous_subscribers' => $previous->subscriber_count,
                ]
            );

            Log::info("Growth spike alert created", [
                'channel' => $channel->channel_name,
                'growth_rate' => $growthRate,
                'threshold' => $threshold
            ]);
        }
    }

    /**
     * Check for viral video alerts
     */
    public function checkViralVideos(YoutubeChannel $channel): void
    {
        $settings = YoutubeAlertSetting::getForUserAndChannel($channel->user_id, $channel->id);
        
        if (!$settings->isAlertEnabled('video_viral')) {
            return;
        }

        $viralSettings = $settings->getSettingForType('video_viral');
        $viewThreshold = $viralSettings['view_threshold'] ?? 100000;

        // Check videos from last 7 days
        $recentVideos = $channel->videos()
            ->with(['snapshots' => function ($query) {
                $query->orderBy('snapshot_date', 'desc')->limit(2);
            }])
            ->where('published_at', '>=', now()->subDays(7))
            ->get();

        foreach ($recentVideos as $video) {
            $snapshots = $video->snapshots;
            
            if ($snapshots->count() < 2) {
                continue;
            }

            $latest = $snapshots->first();
            $previous = $snapshots->last();

            // Check if video crossed viral threshold
            if ($previous->view_count < $viewThreshold && $latest->view_count >= $viewThreshold) {
                YoutubeAlert::createAlert(
                    $channel->user_id,
                    $channel->id,
                    'video_viral',
                    "🔥 Video viral!",
                    "Video \"{$video->title}\" từ kênh {$channel->channel_name} đã đạt {$this->formatNumber($latest->view_count)} views!",
                    [
                        'video_id' => $video->video_id,
                        'video_title' => $video->title,
                        'video_url' => $video->youtube_url,
                        'current_views' => $latest->view_count,
                        'previous_views' => $previous->view_count,
                        'view_growth' => $latest->view_count - $previous->view_count,
                    ]
                );

                Log::info("Viral video alert created", [
                    'channel' => $channel->channel_name,
                    'video' => $video->title,
                    'views' => $latest->view_count
                ]);
            }
        }
    }

    /**
     * Check for inactive channel alerts
     */
    public function checkInactiveChannels(YoutubeChannel $channel): void
    {
        $settings = YoutubeAlertSetting::getForUserAndChannel($channel->user_id, $channel->id);
        
        if (!$settings->isAlertEnabled('channel_inactive')) {
            return;
        }

        $threshold = $settings->getSettingForType('channel_inactive')['threshold'] ?? 30;
        
        $lastVideo = $channel->videos()
            ->orderBy('published_at', 'desc')
            ->first();

        if (!$lastVideo) {
            return;
        }

        $daysSinceLastVideo = $lastVideo->published_at->diffInDays(now());

        if ($daysSinceLastVideo >= $threshold) {
            // Check if we already sent this alert recently
            $existingAlert = YoutubeAlert::where('youtube_channel_id', $channel->id)
                ->where('type', 'channel_inactive')
                ->where('triggered_at', '>=', now()->subDays(7))
                ->exists();

            if (!$existingAlert) {
                YoutubeAlert::createAlert(
                    $channel->user_id,
                    $channel->id,
                    'channel_inactive',
                    "😴 Kênh không hoạt động",
                    "Kênh {$channel->channel_name} đã không đăng video mới trong {$daysSinceLastVideo} ngày",
                    [
                        'days_inactive' => $daysSinceLastVideo,
                        'last_video_date' => $lastVideo->published_at->toISOString(),
                        'last_video_title' => $lastVideo->title,
                    ]
                );

                Log::info("Inactive channel alert created", [
                    'channel' => $channel->channel_name,
                    'days_inactive' => $daysSinceLastVideo
                ]);
            }
        }
    }

    /**
     * Process all alerts for a channel
     */
    public function processChannelAlerts(YoutubeChannel $channel): void
    {
        try {
            $this->checkNewVideoAlerts($channel);
            $this->checkSubscriberMilestones($channel);
            $this->checkGrowthSpikes($channel);
            $this->checkViralVideos($channel);
            $this->checkInactiveChannels($channel);

        } catch (\Exception $e) {
            Log::error("Error processing alerts for channel: {$channel->channel_name}", [
                'channel_id' => $channel->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Format number for display
     */
    private function formatNumber($number, int $decimals = 0): string
    {
        if ($number >= 1000000000) {
            return number_format($number / 1000000000, $decimals) . 'B';
        } elseif ($number >= 1000000) {
            return number_format($number / 1000000, $decimals) . 'M';
        } elseif ($number >= 1000) {
            return number_format($number / 1000, $decimals) . 'K';
        }

        return number_format($number, $decimals);
    }
}
