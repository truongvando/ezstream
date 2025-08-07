<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\YoutubeVideo;
use App\Services\YoutubeApiService;
use Illuminate\Support\Facades\Log;

class CheckYoutubeVideoStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'youtube:check-video-status {--limit=100 : Number of videos to check} {--force : Check all videos regardless of last check time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check YouTube video status and detect dead/removed videos';

    private $youtubeApiService;

    public function __construct(YoutubeApiService $youtubeApiService)
    {
        parent::__construct();
        $this->youtubeApiService = $youtubeApiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Starting YouTube video status check...');

        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        // Get videos that need status check
        $query = YoutubeVideo::with('youtubeChannel');
        
        if (!$force) {
            $query->needsStatusCheck();
        }

        $videos = $query->limit($limit)->get();

        if ($videos->isEmpty()) {
            $this->info('✅ No videos need status check');
            return 0;
        }

        $this->info("📊 Found {$videos->count()} videos to check");

        $checked = 0;
        $stillLive = 0;
        $nowDead = 0;
        $nowPrivate = 0;
        $nowUnlisted = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($videos->count());
        $progressBar->start();

        // Process videos in chunks to avoid API rate limits
        $videoChunks = $videos->chunk(50);

        foreach ($videoChunks as $chunk) {
            $videoIds = $chunk->pluck('video_id')->toArray();
            
            try {
                // Get batch video stats from YouTube API
                $videoStats = $this->youtubeApiService->getBatchVideoStats($videoIds);

                foreach ($chunk as $video) {
                    try {
                        $videoId = $video->video_id;
                        $oldStatus = $video->status;
                        
                        if (isset($videoStats[$videoId])) {
                            // Video found in API response
                            $stats = $videoStats[$videoId];
                            $newStatus = $stats['status'] ?? 'live';
                            
                            $video->update([
                                'status' => $newStatus,
                                'last_checked_at' => now()
                            ]);

                            // Count status changes
                            if ($oldStatus !== $newStatus) {
                                switch ($newStatus) {
                                    case 'live':
                                        $stillLive++;
                                        break;
                                    case 'dead':
                                        $nowDead++;
                                        $this->warn("💀 Video died: {$video->title}");
                                        break;
                                    case 'private':
                                        $nowPrivate++;
                                        $this->warn("🔒 Video private: {$video->title}");
                                        break;
                                    case 'unlisted':
                                        $nowUnlisted++;
                                        $this->warn("👁️ Video unlisted: {$video->title}");
                                        break;
                                }

                                Log::info('Video status changed', [
                                    'video_id' => $videoId,
                                    'title' => $video->title,
                                    'old_status' => $oldStatus,
                                    'new_status' => $newStatus,
                                    'channel' => $video->youtubeChannel->channel_name ?? 'Unknown'
                                ]);
                            } else {
                                $stillLive++;
                            }

                        } else {
                            // Video not found in API response - likely deleted/removed
                            if ($oldStatus !== 'dead') {
                                $video->update([
                                    'status' => 'dead',
                                    'last_checked_at' => now()
                                ]);
                                
                                $nowDead++;
                                $this->error("💀 Video removed/deleted: {$video->title}");
                                
                                Log::warning('Video marked as dead (not found in API)', [
                                    'video_id' => $videoId,
                                    'title' => $video->title,
                                    'old_status' => $oldStatus,
                                    'channel' => $video->youtubeChannel->channel_name ?? 'Unknown'
                                ]);
                            } else {
                                // Already dead, just update check time
                                $video->update(['last_checked_at' => now()]);
                                $stillLive++; // Count as unchanged
                            }
                        }

                        $checked++;

                    } catch (\Exception $e) {
                        $this->error("Error checking video {$video->video_id}: " . $e->getMessage());
                        $errors++;
                        
                        Log::error('Video status check error', [
                            'video_id' => $video->video_id,
                            'error' => $e->getMessage()
                        ]);
                    }

                    $progressBar->advance();
                }

                // Rate limiting between chunks
                if ($videoChunks->count() > 1) {
                    sleep(2);
                }

            } catch (\Exception $e) {
                $this->error("Error processing video chunk: " . $e->getMessage());
                $errors += $chunk->count();
                
                // Still advance progress bar
                for ($i = 0; $i < $chunk->count(); $i++) {
                    $progressBar->advance();
                }
            }
        }

        $progressBar->finish();
        $this->newLine();

        // Summary
        $this->info('✅ Video status check completed!');
        $this->table(
            ['Status', 'Count'],
            [
                ['Checked', $checked],
                ['Still Live', $stillLive],
                ['Now Dead', $nowDead],
                ['Now Private', $nowPrivate],
                ['Now Unlisted', $nowUnlisted],
                ['Errors', $errors]
            ]
        );

        if ($nowDead > 0) {
            $this->warn("⚠️ {$nowDead} videos were marked as dead/removed");
        }

        return 0;
    }
}
