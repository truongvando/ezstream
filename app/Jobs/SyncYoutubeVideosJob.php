<?php

namespace App\Jobs;

use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use App\Services\YoutubeApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncYoutubeVideosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 2;

    private YoutubeChannel $channel;
    private int $maxVideos;

    public function __construct(YoutubeChannel $channel, int $maxVideos = 50)
    {
        $this->channel = $channel;
        $this->maxVideos = $maxVideos;
    }

    /**
     * Execute the job.
     */
    public function handle(YoutubeApiService $youtubeApi): void
    {
        Log::info("🎬 Starting video sync for channel: {$this->channel->channel_name}", [
            'channel_id' => $this->channel->channel_id,
            'max_videos' => $this->maxVideos
        ]);

        try {
            $totalVideos = 0;
            $newVideos = 0;
            $updatedVideos = 0;
            $pageToken = '';

            do {
                // Get videos from YouTube API
                $result = $youtubeApi->getChannelVideos(
                    $this->channel->channel_id, 
                    min(50, $this->maxVideos - $totalVideos),
                    $pageToken
                );

                if (empty($result['videos'])) {
                    Log::warning("No videos returned for channel: {$this->channel->channel_name}");
                    break;
                }

                // Process videos
                $videoIds = array_column($result['videos'], 'video_id');
                $videoStats = $youtubeApi->getBatchVideoStats($videoIds);

                foreach ($result['videos'] as $videoData) {
                    $videoId = $videoData['video_id'];
                    $stats = $videoStats[$videoId] ?? [];

                    $video = $this->syncVideo($videoData, $stats);
                    
                    if ($video->wasRecentlyCreated) {
                        $newVideos++;
                    } else {
                        $updatedVideos++;
                    }

                    $totalVideos++;
                }

                $pageToken = $result['nextPageToken'] ?? '';

                // Rate limiting
                sleep(2);

            } while ($pageToken && $totalVideos < $this->maxVideos);

            Log::info("✅ Video sync completed for channel: {$this->channel->channel_name}", [
                'channel_id' => $this->channel->channel_id,
                'total_processed' => $totalVideos,
                'new_videos' => $newVideos,
                'updated_videos' => $updatedVideos
            ]);

        } catch (\Exception $e) {
            Log::error("❌ Video sync failed for channel: {$this->channel->channel_name}", [
                'channel_id' => $this->channel->channel_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Sync individual video
     */
    private function syncVideo(array $videoData, array $stats): YoutubeVideo
    {
        $video = YoutubeVideo::updateOrCreate(
            [
                'video_id' => $videoData['video_id']
            ],
            [
                'youtube_channel_id' => $this->channel->id,
                'title' => $videoData['title'],
                'description' => $videoData['description'],
                'thumbnail_url' => $videoData['thumbnail_url'],
                'published_at' => $videoData['published_at'],
                'duration_seconds' => $stats['duration_seconds'] ?? null,
                'status' => $stats['status'] ?? 'live',
                'last_checked_at' => now(),
            ]
        );

        // Create video snapshot if we have stats
        if (!empty($stats)) {
            $video->snapshots()->create([
                'view_count' => $stats['view_count'],
                'like_count' => $stats['like_count'],
                'comment_count' => $stats['comment_count'],
                'snapshot_date' => now(),
            ]);
        }

        return $video;
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("❌ SyncYoutubeVideosJob failed permanently", [
            'channel_id' => $this->channel->channel_id,
            'channel_name' => $this->channel->channel_name,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
