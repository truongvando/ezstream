<?php

namespace App\Http\Controllers;

use App\Models\YoutubeChannel;
use App\Services\YoutubeAIAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class YoutubeAIController extends Controller
{
    protected $aiService;

    public function __construct(YoutubeAIAnalysisService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Analyze a single channel with AI
     */
    public function analyzeChannel(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|exists:youtube_channels,id'
        ]);

        $user = Auth::user();
        $channelId = $request->get('channel_id');

        // Get channel with permission check
        $query = YoutubeChannel::where('id', $channelId);
        
        if (!$user->hasRole('admin')) {
            $query->forUser($user->id);
        }

        $channel = $query->first();

        if (!$channel) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy kênh hoặc bạn không có quyền truy cập'
            ], 404);
        }

        try {
            Log::info('Starting comprehensive AI analysis', [
                'channel_id' => $channelId,
                'user_id' => $user->id
            ]);

            Log::info('Starting AI analysis', [
                'channel_id' => $channelId,
                'channel_name' => $channel->channel_name
            ]);

            // Chạy AI analysis sync để có response ngay
            $result = $this->aiService->analyzeChannel($channel);

            if ($result['success']) {
                // Extract RPM từ AI response
                $aiRpm = $this->aiService->extractCpmFromResponse($result['analysis']);

                // Lưu AI analysis vào database
                \DB::table('youtube_ai_analysis')->insert([
                    'youtube_channel_id' => $channel->id,
                    'analysis_content' => $result['analysis'],
                    'extracted_cpm' => $aiRpm,
                    'cpm_source' => 'ai',
                    'analysis_metadata' => json_encode([
                        'channel_data' => $result['channel_data'] ?? null,
                        'video_summary' => $result['video_summary'] ?? null,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Tính doanh thu dựa trên RPM từ AI và views hàng tháng
                $aiService = new YoutubeAIAnalysisService();
                $channelData = $aiService->prepareRawData($channel);
                $monthlyViews = $channelData['channel']['performance_metrics']['estimated_monthly_views'] ?? 0;
                $estimatedRevenue = round($monthlyViews * ($aiRpm / 1000));

                Log::info('AI analysis completed successfully', [
                    'channel_id' => $channelId,
                    'extracted_rpm' => $aiRpm,
                    'estimated_revenue' => $estimatedRevenue
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'channel' => [
                            'id' => $channel->id,
                            'name' => $channel->channel_name,
                            'thumbnail' => $channel->thumbnail_url,
                            'url' => $channel->channel_url
                        ],
                        'analysis' => $result['analysis'],
                        'channel_data' => $result['channel_data'] ?? null,
                        'video_summary' => $result['video_summary'] ?? null,
                        'ai_rpm' => $aiRpm,
                        'estimated_revenue' => $estimatedRevenue,
                        'generated_at' => now()->format('d/m/Y H:i:s')
                    ]
                ]);
            } else {
                Log::error('AI analysis failed', [
                    'channel_id' => $channelId,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Không thể phân tích kênh'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('AI analysis exception', [
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi phân tích kênh: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check AI analysis status for a channel
     */
    public function checkAnalysisStatus(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|exists:youtube_channels,id'
        ]);

        $channelId = $request->channel_id;
        $user = Auth::user();

        try {
            $channel = YoutubeChannel::where('id', $channelId)
                ->where(function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhereHas('user', function($q) {
                              $q->whereHas('roles', function($r) {
                                  $r->where('name', 'admin');
                              });
                          });
                })
                ->firstOrFail();

            // Tìm AI analysis gần nhất
            $latestAnalysis = \DB::table('youtube_ai_analysis')
                ->where('youtube_channel_id', $channel->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestAnalysis) {
                // Có analysis rồi, trả về kết quả với views hàng tháng
                $aiService = new YoutubeAIAnalysisService();
                $channelData = $aiService->prepareRawData($channel);
                $monthlyViews = $channelData['channel']['performance_metrics']['estimated_monthly_views'] ?? 0;
                $estimatedRevenue = round($monthlyViews * ($latestAnalysis->extracted_cpm / 1000));

                return response()->json([
                    'success' => true,
                    'status' => 'completed',
                    'data' => [
                        'channel' => [
                            'id' => $channel->id,
                            'name' => $channel->channel_name,
                            'thumbnail' => $channel->thumbnail_url,
                            'url' => $channel->channel_url
                        ],
                        'analysis' => $latestAnalysis->analysis_content,
                        'ai_cpm' => $latestAnalysis->extracted_cpm,
                        'estimated_revenue' => $estimatedRevenue,
                        'generated_at' => \Carbon\Carbon::parse($latestAnalysis->created_at)->format('d/m/Y H:i:s')
                    ]
                ]);
            } else {
                // Chưa có analysis
                return response()->json([
                    'success' => true,
                    'status' => 'not_found',
                    'message' => 'Chưa có phân tích AI cho kênh này'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Check AI analysis status failed', [
                'channel_id' => $channelId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi kiểm tra trạng thái'
            ], 500);
        }
    }

    /**
     * Compare multiple channels with AI
     */
    public function compareChannels(Request $request)
    {
        $request->validate([
            'channel_ids' => 'required|array|min:2|max:5',
            'channel_ids.*' => 'exists:youtube_channels,id'
        ]);

        $user = Auth::user();
        $channelIds = $request->get('channel_ids');

        // Get channels with permission check
        $query = YoutubeChannel::whereIn('id', $channelIds);
        
        if (!$user->hasRole('admin')) {
            $query->forUser($user->id);
        }

        $channels = $query->get();

        if ($channels->count() < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Cần ít nhất 2 kênh để so sánh'
            ], 400);
        }

        try {
            Log::info('Starting AI channel comparison', [
                'channel_ids' => $channelIds,
                'user_id' => $user->id
            ]);

            $result = $this->aiService->compareChannels($channels->toArray());

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'channels' => $channels->map(function($channel) {
                            return [
                                'id' => $channel->id,
                                'name' => $channel->channel_name,
                                'thumbnail' => $channel->thumbnail_url
                            ];
                        }),
                        'comparison' => $result,
                        'generated_at' => now()->format('d/m/Y H:i:s')
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'] ?? 'Không thể so sánh kênh'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('AI comparison failed', [
                'channel_ids' => $channelIds,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi so sánh với AI: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Custom growth analysis
     */
    private function analyzeGrowth(YoutubeChannel $channel): array
    {
        try {
            $snapshots = $channel->snapshots()
                ->orderBy('snapshot_date', 'desc')
                ->limit(30)
                ->get();

            if ($snapshots->count() < 2) {
                return [
                    'success' => false,
                    'error' => 'Cần ít nhất 2 snapshot để phân tích tăng trưởng'
                ];
            }

            // Calculate growth metrics
            $latest = $snapshots->first();
            $oldest = $snapshots->last();
            $daysDiff = $latest->snapshot_date->diffInDays($oldest->snapshot_date);

            $subscriberGrowth = $latest->subscriber_count - $oldest->subscriber_count;
            $videoGrowth = $latest->video_count - $oldest->video_count;
            $viewGrowth = $latest->view_count - $oldest->view_count;

            $dailySubGrowth = $daysDiff > 0 ? $subscriberGrowth / $daysDiff : 0;
            $dailyVideoGrowth = $daysDiff > 0 ? $videoGrowth / $daysDiff : 0;

            // Prepare data for AI analysis
            $growthData = [
                'channel_name' => $channel->channel_name,
                'analysis_period' => $daysDiff . ' ngày',
                'subscriber_growth' => $subscriberGrowth,
                'video_growth' => $videoGrowth,
                'view_growth' => $viewGrowth,
                'daily_subscriber_growth' => round($dailySubGrowth, 2),
                'daily_video_growth' => round($dailyVideoGrowth, 2),
                'growth_rate' => $oldest->subscriber_count > 0 ? 
                    round(($subscriberGrowth / $oldest->subscriber_count) * 100, 2) : 0,
                'snapshots_data' => $snapshots->map(function($snapshot) {
                    return [
                        'date' => $snapshot->snapshot_date->format('d/m/Y'),
                        'subscribers' => $snapshot->subscriber_count,
                        'videos' => $snapshot->video_count,
                        'views' => $snapshot->view_count
                    ];
                })->toArray()
            ];

            // Create AI prompt for growth analysis
            $prompt = $this->buildGrowthAnalysisPrompt($growthData);
            
            // Call AI service
            $aiResponse = $this->aiService->callOpenAI($prompt);

            return [
                'success' => true,
                'analysis' => $aiResponse,
                'metrics' => $growthData,
                'generated_at' => now()
            ];

        } catch (\Exception $e) {
            Log::error('Growth analysis failed', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Không thể phân tích tăng trưởng: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build growth analysis prompt
     */
    private function buildGrowthAnalysisPrompt(array $data): string
    {
        return "Bạn là chuyên gia phân tích tăng trưởng YouTube. Hãy phân tích dữ liệu tăng trưởng sau:

KÊNH: {$data['channel_name']}
THỜI GIAN PHÂN TÍCH: {$data['analysis_period']}

TĂNG TRƯỞNG TỔNG:
- Subscribers: " . number_format($data['subscriber_growth']) . " (tỷ lệ: {$data['growth_rate']}%)
- Videos: " . number_format($data['video_growth']) . "
- Lượt xem: " . number_format($data['view_growth']) . "

TĂNG TRƯỞNG HÀNG NGÀY:
- Subscribers/ngày: " . number_format($data['daily_subscriber_growth']) . "
- Videos/ngày: " . number_format($data['daily_video_growth']) . "

Hãy phân tích và đưa ra:
1. Đánh giá tốc độ tăng trưởng (chậm/trung bình/nhanh)
2. Xu hướng phát triển (tăng/giảm/ổn định)
3. So sánh với chuẩn ngành
4. Điểm mạnh và yếu trong tăng trưởng
5. Dự đoán tăng trưởng 3 tháng tới
6. Khuyến nghị cải thiện

Trả lời bằng tiếng Việt, có cấu trúc rõ ràng với các đầu mục.";
    }

    /**
     * Debug method to show AI prompt data
     */
    public function debugPrompt($channelId)
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

            $output .= "\n=== FULL PROMPT (FIRST 3000 CHARS) ===\n";
            $output .= substr($data['full_prompt'], 0, 3000) . "...\n";

            return response($output, 200, ['Content-Type' => 'text/plain; charset=utf-8']);

        } catch (\Exception $e) {
            return response("Error: " . $e->getMessage(), 500, ['Content-Type' => 'text/plain']);
        }
    }
}
