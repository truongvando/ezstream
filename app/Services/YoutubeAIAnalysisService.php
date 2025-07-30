<?php

namespace App\Services;

use App\Models\YoutubeChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class YoutubeAIAnalysisService
{
    private string $openaiApiKey;
    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->openaiApiKey = config('services.openai.api_key');
        
        if (!$this->openaiApiKey) {
            throw new \Exception('OpenAI API key not configured');
        }
    }

    /**
     * Analyze a YouTube channel using AI - accepts both ID and object
     */
    public function analyzeChannel($channel): array
    {
        // Handle both int ID and YoutubeChannel object
        if (is_int($channel) || is_string($channel)) {
            $channel = YoutubeChannel::findOrFail($channel);
        }

        if (!$channel instanceof YoutubeChannel) {
            throw new \InvalidArgumentException('Channel must be YoutubeChannel object or valid ID');
        }

        // Check if channel has videos first
        $videoCount = $channel->videos()->count();
        if ($videoCount === 0) {
            return [
                'success' => false,
                'error' => 'Kênh chưa có video nào để phân tích. Vui lòng sync videos trước khi chạy AI Analysis.'
            ];
        }

        $cacheKey = "ai_analysis_channel_{$channel->id}_" . $channel->last_synced_at?->format('Y-m-d');

        return Cache::remember($cacheKey, 3600, function () use ($channel) {
            return $this->performAnalysis($channel);
        });
    }

    /**
     * Perform AI analysis for a channel
     */
    private function performAnalysis(YoutubeChannel $channel): array
    {
        try {
            Log::info('Starting AI analysis', ['channel_id' => $channel->id]);

            // Gather raw data - let AI do the analysis
            $data = $this->prepareRawData($channel);

            // Build simple prompt
            $prompt = $this->buildPrompt($data);

            // Call OpenAI
            $response = $this->callOpenAI($prompt);

            return [
                'success' => true,
                'analysis' => $response,
                'channel_data' => $data['channel'],
                'video_summary' => [
                    'total_videos' => count($data['videos']),
                    'analyzed_videos' => min(count($data['videos']), 20)
                ],
                'generated_at' => now(),
                'channel_id' => $channel->id
            ];

        } catch (\Exception $e) {
            Log::error('AI Analysis failed', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Không thể phân tích kênh với AI: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Prepare comprehensive data for deep AI analysis
     */
    public function prepareRawData(YoutubeChannel $channel): array
    {
        $latest = $channel->latestSnapshot();
        $previous = $channel->snapshots()->orderBy('snapshot_date', 'desc')->skip(1)->first();

        // Channel info with growth metrics
        $channelData = [
            'name' => $this->cleanText($channel->channel_name),
            'description' => $this->cleanText($channel->description),
            'country' => $channel->country,
            'created_at' => $channel->channel_created_at?->format('Y-m-d'),
            'channel_age_days' => $channel->channel_created_at ? $channel->channel_created_at->diffInDays(now()) : 0,
            'subscribers' => $latest?->subscriber_count ?? 0,
            'total_views' => $latest?->view_count ?? 0,
            'total_videos' => $latest?->video_count ?? 0,
            'subscriber_growth_24h' => $previous ? ($latest?->subscriber_count ?? 0) - ($previous->subscriber_count ?? 0) : 0,
            'view_growth_24h' => $previous ? ($latest?->view_count ?? 0) - ($previous->view_count ?? 0) : 0,
            'video_growth_24h' => $previous ? ($latest?->video_count ?? 0) - ($previous->video_count ?? 0) : 0,
        ];

        // Recent videos with detailed metrics (latest 25) - with snapshots
        $videos = $channel->videos()
            ->with(['snapshots' => function($query) {
                $query->latest('snapshot_date');
            }])
            ->orderBy('published_at', 'desc')
            ->limit(25)
            ->get();

        $videoData = [];
        $totalViews = 0;
        $totalLikes = 0;
        $totalComments = 0;
        $publishHours = [];
        $publishDays = [];
        $durations = [];

        $videosWithoutSnapshots = 0;
        foreach ($videos as $video) {
            // Get latest snapshot for video stats
            $snapshot = $video->latestSnapshot();
            if (!$snapshot) {
                $videosWithoutSnapshots++;
            }
            $views = $snapshot ? $snapshot->view_count : 0;
            $likes = $snapshot ? $snapshot->like_count : 0;
            $comments = $snapshot ? $snapshot->comment_count : 0;

            // Parse duration to seconds
            $durationSeconds = $this->parseDurationToSeconds($video->duration);

            $videoData[] = [
                'title' => $this->cleanText($video->title),
                'description' => $this->cleanText(substr($video->description ?? '', 0, 400)),
                'published_at' => $video->published_at?->format('Y-m-d H:i'),
                'publish_hour' => $video->published_at?->format('H') ?? 0,
                'publish_day' => $video->published_at?->format('N') ?? 0, // 1=Monday
                'views' => $views,
                'likes' => $likes,
                'comments' => $comments,
                'duration' => $video->duration,
                'duration_seconds' => $durationSeconds,
                'engagement_rate' => $views > 0 ? round((($likes + $comments) / $views) * 100, 3) : 0,
                'like_ratio' => $views > 0 ? round(($likes / $views) * 100, 3) : 0,
                'comment_ratio' => $views > 0 ? round(($comments / $views) * 100, 3) : 0,
                'days_since_published' => $video->published_at ? $video->published_at->diffInDays(now()) : 0,
            ];

            // Collect stats for analysis
            $totalViews += $views;
            $totalLikes += $likes;
            $totalComments += $comments;
            if ($video->published_at) {
                $publishHours[] = (int)$video->published_at->format('H');
                $publishDays[] = (int)$video->published_at->format('N');
            }
            if ($durationSeconds > 0) {
                $durations[] = $durationSeconds;
            }
        }

        // Calculate performance metrics for analysis
        $videoCount = count($videos);
        $channelTotalViews = $channelData['total_views'];
        $channelTotalVideos = $channelData['total_videos'];
        $channelAge = $channelData['channel_age_days'];

        // Find best and worst performing videos
        $videosByViews = collect($videoData)->sortByDesc('views');
        $bestVideo = $videosByViews->first();
        $worstVideo = $videosByViews->last();

        // Debug: Log video data to check views
        \Log::info('Video data sample:', [
            'total_videos' => count($videoData),
            'videos_without_snapshots' => $videosWithoutSnapshots,
            'best_video' => $bestVideo,
            'worst_video' => $worstVideo,
            'first_3_videos' => array_slice($videoData, 0, 3)
        ]);

        // Calculate key metrics
        $overallEngagementRate = $channelTotalViews > 0 ?
            round((($channelData['subscribers']) / $channelTotalViews) * 100, 4) : 0;

        $recentEngagementRate = $totalViews > 0 ?
            round((($totalLikes + $totalComments) / $totalViews) * 100, 3) : 0;

        $publishingFrequency = $channelAge > 0 ?
            round(($channelTotalVideos / $channelAge) * 7, 2) : 0; // videos per week

        $avgViewsPerVideo = $channelTotalVideos > 0 ?
            round($channelTotalViews / $channelTotalVideos) : 0;

        $recentAvgViews = $videoCount > 0 ? round($totalViews / $videoCount) : 0;

        // Estimate monthly views based on recent performance
        $estimatedMonthlyViews = $publishingFrequency > 0 ?
            round(($publishingFrequency * 4.33) * $recentAvgViews) : 0; // 4.33 weeks per month

        // Performance comparison
        $performanceGap = $bestVideo && $worstVideo ?
            round($bestVideo['views'] / max($worstVideo['views'], 1), 1) : 0;

        $channelData['performance_metrics'] = [
            'overall_engagement_rate' => $overallEngagementRate,
            'recent_engagement_rate' => $recentEngagementRate,
            'publishing_frequency_per_week' => $publishingFrequency,
            'avg_views_per_video' => $avgViewsPerVideo,
            'recent_avg_views' => $recentAvgViews,
            'estimated_monthly_views' => $estimatedMonthlyViews,
            'best_video' => $bestVideo,
            'worst_video' => $worstVideo,
            'performance_gap' => $performanceGap,
            'total_recent_engagement' => $totalLikes + $totalComments,
            'avg_duration_minutes' => count($durations) > 0 ? round(array_sum($durations) / count($durations) / 60, 1) : 0,
            'most_common_publish_hour' => count($publishHours) > 0 ? $this->getMostCommon($publishHours) : null,
            'most_common_publish_day' => count($publishDays) > 0 ? $this->getMostCommon($publishDays) : null,
        ];

        return [
            'channel' => $channelData,
            'videos' => $videoData
        ];
    }

    /**
     * Parse duration string to seconds
     */
    private function parseDurationToSeconds(?string $duration): int
    {
        if (empty($duration)) return 0;

        // Handle PT format (PT1H2M3S)
        if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches)) {
            $hours = (int)($matches[1] ?? 0);
            $minutes = (int)($matches[2] ?? 0);
            $seconds = (int)($matches[3] ?? 0);
            return $hours * 3600 + $minutes * 60 + $seconds;
        }

        // Handle MM:SS or HH:MM:SS format
        if (preg_match('/^(?:(\d+):)?(\d+):(\d+)$/', $duration, $matches)) {
            $hours = (int)($matches[1] ?? 0);
            $minutes = (int)$matches[2];
            $seconds = (int)$matches[3];
            return $hours * 3600 + $minutes * 60 + $seconds;
        }

        return 0;
    }

    /**
     * Get most common value from array
     */
    private function getMostCommon(array $values): mixed
    {
        if (empty($values)) return null;
        $counts = array_count_values($values);
        arsort($counts);
        return array_key_first($counts);
    }

    /**
     * Build detailed prompt for deep AI analysis
     */
    private function buildPrompt(array $data): string
    {
        $channel = $data['channel'];
        $videos = $data['videos'];

        $metrics = $channel['performance_metrics'];
        $bestVideo = $metrics['best_video'];
        $worstVideo = $metrics['worst_video'];

        $prompt = "Bạn là chuyên gia phân tích YouTube và monetization hàng đầu. Hãy phân tích toàn diện kênh YouTube sau đây:

═══════════════════════════════════════════════════════════════
📺 THÔNG TIN KÊNH CƠ BẢN
═══════════════════════════════════════════════════════════════
• Tên kênh: {$channel['name']}
• Mô tả: " . (strlen($channel['description']) > 200 ? substr($channel['description'], 0, 200) . '...' : $channel['description']) . "
• Ngày tạo: {$channel['created_at']} (Tuổi kênh: {$channel['channel_age_days']} ngày = " . round($channel['channel_age_days'] / 30.44, 1) . " tháng)
• Quốc gia: {$channel['country']}
• Handle: " . ($channel['channel_handle'] ?? 'N/A') . "

═══════════════════════════════════════════════════════════════
📊 THỐNG KÊ HIỆN TẠI
═══════════════════════════════════════════════════════════════
• Subscribers: " . number_format($channel['subscribers']) . "
• Tổng videos: " . number_format($channel['total_videos']) . "
• Tổng lượt xem: " . number_format($channel['total_views']) . "
• Trung bình views/video: " . number_format($metrics['avg_views_per_video']) . "

═══════════════════════════════════════════════════════════════
⚡ CHỈ SỐ HIỆU SUẤT
═══════════════════════════════════════════════════════════════
• Engagement Rate (Gần đây): {$metrics['recent_engagement_rate']}%
• Views gần đây/video: " . number_format($metrics['recent_avg_views']) . "
• Ước tính views/tháng: " . number_format($metrics['estimated_monthly_views']) . " (dựa trên tần suất đăng hiện tại)
• Tần suất đăng: {$metrics['publishing_frequency_per_week']} videos/tuần
• Độ dài trung bình: {$metrics['avg_duration_minutes']} phút
• Giờ đăng phổ biến nhất: {$metrics['most_common_publish_hour']}:00
• Ngày đăng phổ biến nhất: Thứ {$metrics['most_common_publish_day']}

═══════════════════════════════════════════════════════════════
🏆 VIDEO PERFORMANCE
═══════════════════════════════════════════════════════════════";

        // Add best/worst video info with null checks
        if ($bestVideo && isset($bestVideo['title'], $bestVideo['views'])) {
            $prompt .= "\n• Video tốt nhất: \"{$bestVideo['title']}\" - " . number_format($bestVideo['views']) . " views";
        } else {
            $prompt .= "\n• Video tốt nhất: Không có dữ liệu";
        }

        if ($worstVideo && isset($worstVideo['title'], $worstVideo['views'])) {
            $prompt .= "\n• Video yếu nhất: \"{$worstVideo['title']}\" - " . number_format($worstVideo['views']) . " views";
        } else {
            $prompt .= "\n• Video yếu nhất: Không có dữ liệu";
        }

        $prompt .= "\n• Performance Gap: {$metrics['performance_gap']}x (tốt nhất vs yếu nhất)

═══════════════════════════════════════════════════════════════
🎬 CHI TIẾT VIDEOS GẦN ĐÂY (" . count($videos) . " videos)
═══════════════════════════════════════════════════════════════";

        foreach (array_slice($videos, 0, 20) as $i => $video) {
            $views = $video['views'] ?? 0;
            $likes = $video['likes'] ?? 0;
            $comments = $video['comments'] ?? 0;
            $engagementRate = $views > 0 ? round((($likes + $comments) / $views) * 100, 3) : 0;

            $prompt .= "\n\n📹 " . ($i + 1) . ". {$video['title']}";
            $prompt .= "\n   📅 Ngày: {$video['published_at']}";
            $prompt .= "\n   👁️ Views: " . number_format($views);
            $prompt .= "\n   👍 Likes: " . number_format($likes);
            $prompt .= "\n   💬 Comments: " . number_format($comments);
            $prompt .= "\n   📊 Engagement: {$engagementRate}%";

            if (!empty($video['description']) && strlen($video['description']) > 50) {
                $prompt .= "\n   📝 Mô tả: " . substr($video['description'], 0, 100) . "...";
            }
        }

        $prompt .= "

═══════════════════════════════════════════════════════════════
🎯 YÊU CẦU PHÂN TÍCH
═══════════════════════════════════════════════════════════════

Hãy phân tích toàn diện và đưa ra báo cáo chi tiết bao gồm:

1. 📋 ĐÁNH GIÁ TỔNG QUAN
- Xếp hạng hiệu suất kênh (1-10 điểm)
- Điểm mạnh nổi bật nhất
- Điểm yếu cần cải thiện ngay
- So sánh với chuẩn ngành

2. 📊 PHÂN TÍCH HIỆU SUẤT
- Đánh giá tốc độ tăng trưởng
- Phân tích engagement rate
- Hiệu quả content (views/video)
- Tỷ lệ chuyển đổi viewer thành subscriber

3. 🎬 CHIẾN LƯỢC CONTENT
- Loại content hoạt động tốt nhất
- Thời điểm đăng video tối ưu
- Độ dài video lý tưởng
- Tần suất đăng video khuyến nghị

4. 📈 DỰ ĐOÁN & XU HƯỚNG
- Dự báo tăng trưởng 3 tháng tới
- Xu hướng phát triển của kênh
- Cơ hội và thách thức sắp tới

5. 💡 KHUYẾN NGHỊ CỤ THỂ
- 5 hành động ưu tiên cao nhất
- Chiến lược tăng subscriber
- Cách cải thiện engagement
- Kế hoạch phát triển dài hạn

6. 💰 PHÂN TÍCH MONETIZATION (QUAN TRỌNG)
- Phân tích ngôn ngữ và thị trường chính của kênh dựa trên tiêu đề video
- Xác định đối tượng khán giả chính và vùng địa lý từ nội dung
- Đánh giá niche content và mức độ cạnh tranh trong thị trường
- Ước tính RPM (Revenue Per Mille - USD/1000 views) bằng cách phân tích sâu:
  * Ngôn ngữ tiêu đề → xác định thị trường chính (VN/JP/KR/US/Global)
  * Niche content → Music/BGM thường có RPM thấp hơn Tech/Finance
  * Engagement quality → High engagement = Premium ad placements
  * Audience demographics → Age, location, purchasing power
  * Advertiser demand → Seasonal trends, market saturation
  * Content length → Longer videos = More ad opportunities
- CÁCH TÍNH DOANH THU: Sử dụng views HÀNG THÁNG (không phải tổng views) nhân với RPM ước tính
- Công thức: (Views hàng tháng / 1000) × RPM = Doanh thu AdSense/tháng
- Phân tích tiềm năng kiếm tiền từ các nguồn khác:
  * Sponsorship và brand partnerships
  * Affiliate marketing
  * Merchandise và sản phẩm riêng
  * Membership và Patreon
- Đưa ra con số RPM cụ thể và giải thích lý do
- So sánh với benchmark ngành và đưa ra dự đoán tăng trưởng doanh thu

7. ⚠️ CẢNH BÁO & RỦI RO
- Những dấu hiệu cần chú ý
- Rủi ro tiềm ẩn
- Cách phòng tránh suy giảm

═══════════════════════════════════════════════════════════════

**QUAN TRỌNG:**
Bạn PHẢI đưa ra một con số RPM cụ thể duy nhất (ví dụ: \$0.65), KHÔNG được đưa ra range.
Phân tích sâu và quyết định dựa trên:
▸ Ngôn ngữ và thị trường chính từ tiêu đề video
▸ Thể loại nội dung và mức độ advertiser-friendly
▸ Engagement quality và demographics khán giả
▸ Seasonal factors và market conditions hiện tại
▸ Competition level trong niche này

Sau khi phân tích, hãy đưa ra:
1. Con số RPM chính xác (ví dụ: \$0.73)
2. Giải thích chi tiết tại sao chọn con số này
3. Tính toán doanh thu hàng tháng cụ thể
4. So sánh với industry benchmarks

**YÊU CẦU VIẾT:**
- Sử dụng format rõ ràng với tiêu đề đậm cho từng phần
- Mỗi phần phân tích phải có tiêu đề riêng biệt và nội dung chi tiết
- Sử dụng ký hiệu ▸ để đánh dấu các điểm quan trọng
- Phân tích dựa trên số liệu cụ thể, giải thích WHY và HOW
- Tone chuyên nghiệp nhưng dễ hiểu, có thể học hỏi được
- Viết như đang tư vấn trực tiếp cho client
- TRONG PHẦN MONETIZATION: Bắt buộc phải có dòng \"▸ RPM ước tính: \$X.XX\" với con số cụ thể

**FORMAT MẪU:**
**ĐÁNH GIÁ TỔNG QUAN**
Nội dung phân tích tổng quan...
▸ Điểm quan trọng 1
▸ Điểm quan trọng 2

**PHÂN TÍCH HIỆU SUẤT**
Nội dung phân tích hiệu suất...
▸ Insight quan trọng

**MONETIZATION**
Phân tích thị trường từ tiêu đề video...
▸ RPM ước tính: \$X.X
▸ Doanh thu hàng tháng: \$XXX

Hãy tuân thủ format này để báo cáo dễ đọc và chuyên nghiệp.";

        // Debug: Log the complete prompt being sent
        \Log::info('=== PROMPT BEING SENT TO AI ===');
        \Log::info('Channel: ' . $data['channel']['name']);
        \Log::info('Subscribers: ' . number_format($data['channel']['subscribers']));
        \Log::info('Total Views: ' . number_format($data['channel']['total_views']));
        \Log::info('Videos count: ' . count($data['videos']));

        // Log performance metrics
        $metrics = $data['channel']['performance_metrics'];
        \Log::info('=== PERFORMANCE METRICS ===');
        \Log::info('Recent Engagement Rate: ' . $metrics['recent_engagement_rate'] . '%');
        \Log::info('Recent Avg Views: ' . number_format($metrics['recent_avg_views']));
        \Log::info('Estimated Monthly Views: ' . number_format($metrics['estimated_monthly_views']));

        // Log sample videos
        \Log::info('=== SAMPLE VIDEOS ===');
        foreach (array_slice($data['videos'], 0, 3) as $i => $video) {
            \Log::info(($i + 1) . ". " . $video['title']);
            \Log::info("   Views: " . number_format($video['views']));
            \Log::info("   Likes: " . number_format($video['likes']));
            \Log::info("   Comments: " . number_format($video['comments']));
        }

        \Log::info('=== PROMPT PREVIEW (FIRST 1500 CHARS) ===');
        \Log::info(substr($prompt, 0, 1500));
        \Log::info('=== END PROMPT DEBUG ===');

        return $prompt;
    }

    /**
     * Debug method to show what data is being sent
     */
    public function debugPromptData($channelId): array
    {
        $channel = YoutubeChannel::findOrFail($channelId);
        $data = $this->prepareRawData($channel);
        $prompt = $this->buildPrompt($data);

        return [
            'channel_data' => $data['channel'],
            'videos_count' => count($data['videos']),
            'sample_videos' => array_slice($data['videos'], 0, 3),
            'full_prompt' => $prompt
        ];
    }

    /**
     * Call OpenAI API
     */
    private function callOpenAI(string $prompt): string
    {
        $cleanPrompt = $this->cleanText($prompt);

        $response = Http::timeout(180)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là Senior YouTube Growth Strategist với 15 năm kinh nghiệm. Viết báo cáo phân tích bằng văn bản thuần túy, TUYỆT ĐỐI KHÔNG dùng markdown, **, ##, bullet points, hay bất kỳ ký tự format nào. Viết như báo cáo kinh doanh thực tế với các đoạn văn liền mạch, insights sâu sắc và actionable.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $cleanPrompt
                    ]
                ],
                'max_tokens' => 6000,
                'temperature' => 0.3,
            ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API error: ' . $response->body());
        }

        $data = $response->json();
        return $data['choices'][0]['message']['content'] ?? 'Không thể tạo phân tích';
    }

    /**
     * Clean text for UTF-8 safety
     */
    private function cleanText(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Convert to UTF-8 and remove problematic characters
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        // Remove control characters but keep newlines
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Replace replacement characters
        $text = str_replace(['�', "\xEF\xBF\xBD"], '[?]', $text);
        
        // Ensure valid UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        return trim($text);
    }

    /**
     * Extract CPM from AI response
     */
    public function extractCpmFromResponse(string $aiResponse): float
    {
        // Look for RPM/CPM patterns in response
        $patterns = [
            '/RPM.*?(\$?[\d,]+\.?\d*)/i',
            '/CPM.*?(\$?[\d,]+\.?\d*)/i',
            '/(\$?[\d,]+\.?\d*).*?USD.*?1000.*?views/i',
            '/(\$?[\d,]+\.?\d*).*?per.*?1000.*?views/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $aiResponse, $matches)) {
                $cpmString = $matches[1];
                $cpmValue = (float) preg_replace('/[^\d.]/', '', $cpmString);
                
                if ($cpmValue >= 0.1 && $cpmValue <= 20.0) {
                    return $cpmValue;
                }
            }
        }

        return 1.0; // Default CPM
    }
}
