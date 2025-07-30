<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\YoutubeAIAnalysisService;

class DebugController extends Controller
{
    public function showAIPrompt($channelId)
    {
        try {
            $service = new YoutubeAIAnalysisService();
            $data = $service->debugPromptData($channelId);
            
            // Format for easy reading
            $output = "=== DỮ LIỆU KÊNH ===\n";
            $output .= "ID: " . $data['channel_data']['id'] . "\n";
            $output .= "Tên: " . $data['channel_data']['name'] . "\n";
            $output .= "Subscribers: " . number_format($data['channel_data']['subscribers']) . "\n";
            $output .= "Total Views: " . number_format($data['channel_data']['total_views']) . "\n";
            $output .= "Total Videos: " . number_format($data['channel_data']['total_videos']) . "\n";
            $output .= "Country: " . $data['channel_data']['country'] . "\n";
            
            $output .= "\n=== METRICS ===\n";
            $metrics = $data['channel_data']['performance_metrics'];
            $output .= "Recent Engagement Rate: " . $metrics['recent_engagement_rate'] . "%\n";
            $output .= "Avg Views/Video: " . number_format($metrics['avg_views_per_video']) . "\n";
            $output .= "Recent Avg Views: " . number_format($metrics['recent_avg_views']) . "\n";
            $output .= "Estimated Monthly Views: " . number_format($metrics['estimated_monthly_views']) . "\n";
            $output .= "Publishing Frequency: " . $metrics['publishing_frequency_per_week'] . " videos/week\n";
            
            $output .= "\n=== SAMPLE VIDEOS ===\n";
            foreach ($data['sample_videos'] as $i => $video) {
                $output .= ($i + 1) . ". " . $video['title'] . "\n";
                $output .= "   Views: " . number_format($video['views']) . "\n";
                $output .= "   Likes: " . number_format($video['likes']) . "\n";
                $output .= "   Comments: " . number_format($video['comments']) . "\n";
                $output .= "   Published: " . $video['published_at'] . "\n\n";
            }
            
            $output .= "\n=== FULL PROMPT (FIRST 2000 CHARS) ===\n";
            $output .= substr($data['full_prompt'], 0, 2000) . "...\n";
            
            return response($output, 200, ['Content-Type' => 'text/plain; charset=utf-8']);
            
        } catch (\Exception $e) {
            return response("Error: " . $e->getMessage(), 500, ['Content-Type' => 'text/plain']);
        }
    }
}
