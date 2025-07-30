<?php

namespace App\Jobs;

use App\Models\YoutubeChannel;
use App\Services\YoutubeAIAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessYoutubeAIAnalysisJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 2;

    protected $channel;

    /**
     * Create a new job instance.
     */
    public function __construct(YoutubeChannel $channel)
    {
        $this->channel = $channel;
    }

    /**
     * Execute the job.
     */
    public function handle(YoutubeAIAnalysisService $aiService): void
    {
        try {
            Log::info('Starting AI analysis job', [
                'channel_id' => $this->channel->id,
                'channel_name' => $this->channel->channel_name
            ]);

            $result = $aiService->analyzeChannel($this->channel);

            if ($result['success']) {
                // Extract CPM từ AI response
                $aiCpm = $aiService->extractCpmFromResponse($result['analysis']);

                // Lưu AI analysis vào database
                \DB::table('youtube_ai_analysis')->insert([
                    'youtube_channel_id' => $this->channel->id,
                    'analysis_content' => $result['analysis'],
                    'extracted_cpm' => $aiCpm,
                    'cpm_source' => 'ai',
                    'analysis_metadata' => json_encode([
                        'channel_data' => $result['channel_data'] ?? null,
                        'video_summary' => $result['video_summary'] ?? null,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                Log::info('AI analysis job completed successfully', [
                    'channel_id' => $this->channel->id,
                    'extracted_cpm' => $aiCpm
                ]);
            } else {
                Log::error('AI analysis job failed - detailed error', [
                    'channel_id' => $this->channel->id,
                    'channel_name' => $this->channel->channel_name,
                    'error' => $result['error'] ?? 'Unknown error',
                    'full_result' => $result
                ]);

                // Throw exception để job retry
                throw new \Exception('AI analysis failed: ' . ($result['error'] ?? 'Unknown error'));
            }

        } catch (\Exception $e) {
            Log::error('AI analysis job exception', [
                'channel_id' => $this->channel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e; // Re-throw để job retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AI analysis job failed permanently', [
            'channel_id' => $this->channel->id,
            'exception' => $exception->getMessage()
        ]);
    }
}
