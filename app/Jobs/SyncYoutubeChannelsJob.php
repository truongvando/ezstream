<?php

namespace App\Jobs;

use App\Models\YoutubeChannel;
use App\Services\YoutubeApiService;
use App\Services\YoutubeAlertService;
use App\Jobs\SyncYoutubeVideosJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncYoutubeChannelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(YoutubeApiService $youtubeApi, YoutubeAlertService $alertService): void
    {
        Log::info('🔄 Starting YouTube channels sync job');

        try {
            // Get channels that need sync (active channels not synced in last 24 hours)
            $channelsToSync = YoutubeChannel::active()
                ->needsSync()
                ->get();

            if ($channelsToSync->isEmpty()) {
                Log::info('✅ No YouTube channels need sync');
                return;
            }

            Log::info("📊 Found {$channelsToSync->count()} channels to sync");

            // Process channels in batches of 50 (YouTube API limit)
            $channelIds = $channelsToSync->pluck('channel_id')->toArray();
            $chunks = array_chunk($channelIds, 50);

            $totalSynced = 0;
            $totalErrors = 0;

            foreach ($chunks as $chunkIndex => $chunk) {
                Log::info("🔄 Processing batch " . ($chunkIndex + 1) . "/" . count($chunks) . " (" . count($chunk) . " channels)");

                try {
                    // Get batch channel info from YouTube API
                    $channelsData = $youtubeApi->getBatchChannelsInfo($chunk);

                    foreach ($chunk as $channelId) {
                        try {
                            $channel = $channelsToSync->where('channel_id', $channelId)->first();
                            
                            if (!$channel) {
                                Log::warning("Channel not found in local collection: {$channelId}");
                                continue;
                            }

                            if (isset($channelsData[$channelId])) {
                                $this->syncChannel($channel, $channelsData[$channelId]);

                                // Process alerts for this channel
                                $alertService->processChannelAlerts($channel);

                                // Dispatch video sync job for this channel (delay 5 seconds to avoid rate limit)
                                SyncYoutubeVideosJob::dispatch($channel, 20)->delay(now()->addSeconds(5));

                                $totalSynced++;
                            } else {
                                Log::warning("Channel data not returned from API: {$channelId}");
                                $totalErrors++;
                            }

                        } catch (\Exception $e) {
                            Log::error("Error syncing individual channel {$channelId}", [
                                'error' => $e->getMessage(),
                                'channel_name' => $channel->channel_name ?? 'Unknown'
                            ]);
                            $totalErrors++;
                        }
                    }

                    // Rate limiting - wait between batches
                    if ($chunkIndex < count($chunks) - 1) {
                        sleep(2); // 2 seconds between batches
                    }

                } catch (\Exception $e) {
                    Log::error("Error processing batch " . ($chunkIndex + 1), [
                        'error' => $e->getMessage(),
                        'chunk_size' => count($chunk)
                    ]);
                    $totalErrors += count($chunk);
                }
            }

            Log::info("✅ YouTube channels sync completed", [
                'total_channels' => $channelsToSync->count(),
                'synced' => $totalSynced,
                'errors' => $totalErrors
            ]);

        } catch (\Exception $e) {
            Log::error('❌ YouTube channels sync job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Sync individual channel data
     */
    private function syncChannel(YoutubeChannel $channel, array $channelData): void
    {
        try {
            // Update channel info
            $channel->update([
                'channel_name' => $channelData['channel_name'],
                'description' => $channelData['description'],
                'thumbnail_url' => $channelData['thumbnail_url'],
                'country' => $channelData['country'],
                'last_synced_at' => now(),
            ]);

            // Create new snapshot
            $channel->snapshots()->create([
                'subscriber_count' => $channelData['subscriber_count'],
                'video_count' => $channelData['video_count'],
                'view_count' => $channelData['view_count'],
                'comment_count' => 0, // Will be updated when we sync videos
                'snapshot_date' => now(),
            ]);

            Log::info("✅ Synced channel: {$channel->channel_name}", [
                'channel_id' => $channel->channel_id,
                'subscribers' => $channelData['subscriber_count'],
                'videos' => $channelData['video_count'],
                'views' => $channelData['view_count']
            ]);

        } catch (\Exception $e) {
            Log::error("Error updating channel data", [
                'channel_id' => $channel->channel_id,
                'channel_name' => $channel->channel_name,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('❌ SyncYoutubeChannelsJob failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
