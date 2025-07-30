<?php

namespace App\Jobs;

use App\Models\YoutubeChannel;
use App\Models\YoutubeVideo;
use App\Models\YoutubeVideoSnapshot;
use App\Services\YoutubeApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncAllChannelVideosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $channel;
    protected $pageToken;
    protected $maxPages;
    protected $currentPage;

    /**
     * Create a new job instance.
     */
    public function __construct(YoutubeChannel $channel, ?string $pageToken = null, int $maxPages = 20, int $currentPage = 1)
    {
        $this->channel = $channel;
        $this->pageToken = $pageToken;
        $this->maxPages = $maxPages; // Giới hạn tối đa 20 pages = 1000 videos
        $this->currentPage = $currentPage;
    }

    /**
     * Execute the job.
     */
    public function handle(YoutubeApiService $youtubeApi): void
    {
        try {
            Log::info('Starting full channel video sync', [
                'channel_id' => $this->channel->id,
                'page' => $this->currentPage,
                'max_pages' => $this->maxPages,
                'page_token' => $this->pageToken ? 'present' : 'null'
            ]);

            // Lấy 50 videos tiếp theo
            $videoData = $youtubeApi->getChannelVideos(
                $this->channel->channel_id, 
                50, 
                $this->pageToken ?? ''
            );

            if (empty($videoData['videos'])) {
                Log::info('No more videos found, sync completed', [
                    'channel_id' => $this->channel->id,
                    'total_pages_processed' => $this->currentPage
                ]);
                return;
            }

            // Lấy chi tiết videos
            $videoIds = array_column($videoData['videos'], 'video_id');
            $videoDetails = $youtubeApi->getVideoDetailsForAI($videoIds);
            
            $syncedCount = 0;
            $skippedCount = 0;

            foreach ($videoDetails as $videoDetail) {
                // Kiểm tra video đã tồn tại chưa
                $existingVideo = YoutubeVideo::where('video_id', $videoDetail['video_id'])
                    ->where('youtube_channel_id', $this->channel->id)
                    ->first();

                if ($existingVideo) {
                    $skippedCount++;
                    continue; // Skip nếu đã có
                }

                // Tạo video mới
                $video = YoutubeVideo::create([
                    'video_id' => $videoDetail['video_id'],
                    'youtube_channel_id' => $this->channel->id,
                    'title' => $videoDetail['title'],
                    'description' => $videoDetail['description'],
                    'thumbnail_url' => $videoDetail['thumbnail_url'],
                    'published_at' => $videoDetail['published_at'],
                    'duration' => $videoDetail['duration'] ?? null,
                    'duration_seconds' => $videoDetail['duration_seconds'] ?? 0,
                    'status' => 'active'
                ]);

                // Tạo snapshot
                YoutubeVideoSnapshot::create([
                    'youtube_video_id' => $video->id,
                    'snapshot_date' => now()->toDateString(),
                    'view_count' => $videoDetail['view_count'],
                    'like_count' => $videoDetail['like_count'],
                    'comment_count' => $videoDetail['comment_count']
                ]);

                $syncedCount++;
            }

            Log::info('Page sync completed', [
                'channel_id' => $this->channel->id,
                'page' => $this->currentPage,
                'synced' => $syncedCount,
                'skipped' => $skippedCount,
                'has_next_page' => !empty($videoData['nextPageToken'])
            ]);

            // Tiếp tục với page tiếp theo nếu có và chưa đạt giới hạn
            if (!empty($videoData['nextPageToken']) && $this->currentPage < $this->maxPages) {
                // Dispatch job tiếp theo với delay để tránh rate limit
                self::dispatch(
                    $this->channel, 
                    $videoData['nextPageToken'], 
                    $this->maxPages, 
                    $this->currentPage + 1
                )->delay(now()->addSeconds(2));
                
                Log::info('Next page job dispatched', [
                    'channel_id' => $this->channel->id,
                    'next_page' => $this->currentPage + 1,
                    'next_token' => substr($videoData['nextPageToken'], 0, 10) . '...'
                ]);
            } else {
                // Hoàn thành sync
                $totalVideos = YoutubeVideo::where('youtube_channel_id', $this->channel->id)->count();
                
                Log::info('Full channel video sync completed', [
                    'channel_id' => $this->channel->id,
                    'total_pages_processed' => $this->currentPage,
                    'total_videos_in_db' => $totalVideos,
                    'reason' => empty($videoData['nextPageToken']) ? 'no_more_videos' : 'max_pages_reached'
                ]);

                // Cập nhật thời gian sync cuối
                $this->channel->update([
                    'last_video_sync_at' => now()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Full video sync failed', [
                'channel_id' => $this->channel->id,
                'page' => $this->currentPage,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Retry với exponential backoff
            if ($this->attempts() < 3) {
                $delay = pow(2, $this->attempts()) * 60; // 2, 4, 8 minutes
                $this->release($delay);
            } else {
                Log::error('Full video sync failed permanently', [
                    'channel_id' => $this->channel->id,
                    'page' => $this->currentPage,
                    'attempts' => $this->attempts()
                ]);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncAllChannelVideosJob failed permanently', [
            'channel_id' => $this->channel->id,
            'page' => $this->currentPage,
            'exception' => $exception->getMessage()
        ]);
    }
}
