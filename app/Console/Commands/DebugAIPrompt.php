<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YoutubeAIAnalysisService;

class DebugAIPrompt extends Command
{
    protected $signature = 'debug:ai-prompt {channelId}';
    protected $description = 'Debug AI prompt data for a channel';

    public function handle()
    {
        $channelId = $this->argument('channelId');
        
        try {
            $service = new YoutubeAIAnalysisService();
            $data = $service->debugPromptData($channelId);

            $this->info('=== RAW DATA STRUCTURE ===');
            $this->line('Keys in data: ' . implode(', ', array_keys($data)));
            $this->line('Keys in channel_data: ' . implode(', ', array_keys($data['channel_data'])));

            $this->info('=== DỮ LIỆU KÊNH ===');
            $channel = $data['channel_data'];
            $this->line('Name: ' . ($channel['name'] ?? 'N/A'));
            $this->line('Subscribers: ' . number_format($channel['subscribers'] ?? 0));
            $this->line('Total Views: ' . number_format($channel['total_views'] ?? 0));
            $this->line('Total Videos: ' . number_format($channel['total_videos'] ?? 0));
            $this->line('Country: ' . ($channel['country'] ?? 'N/A'));

            if (isset($channel['performance_metrics'])) {
                $this->info('=== METRICS ===');
                $metrics = $channel['performance_metrics'];
                $this->line('Recent Engagement Rate: ' . ($metrics['recent_engagement_rate'] ?? 0) . '%');
                $this->line('Avg Views/Video: ' . number_format($metrics['avg_views_per_video'] ?? 0));
                $this->line('Recent Avg Views: ' . number_format($metrics['recent_avg_views'] ?? 0));
                $this->line('Estimated Monthly Views: ' . number_format($metrics['estimated_monthly_views'] ?? 0));
                $this->line('Publishing Frequency: ' . ($metrics['publishing_frequency_per_week'] ?? 0) . ' videos/week');
            }

            $this->info('=== SAMPLE VIDEOS ===');
            $this->line('Videos count: ' . $data['videos_count']);
            foreach ($data['sample_videos'] as $i => $video) {
                $this->line(($i + 1) . '. ' . $video['title']);
                $this->line('   Views: ' . number_format($video['views'] ?? 0));
                $this->line('   Likes: ' . number_format($video['likes'] ?? 0));
                $this->line('   Comments: ' . number_format($video['comments'] ?? 0));
                $this->line('   Published: ' . ($video['published_at'] ?? 'N/A'));
                $this->line('');
            }

            $this->info('=== PROMPT PREVIEW (FIRST 2000 CHARS) ===');
            $this->line(substr($data['full_prompt'], 0, 2000) . '...');

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
