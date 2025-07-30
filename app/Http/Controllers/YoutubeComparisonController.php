<?php

namespace App\Http\Controllers;

use App\Models\YoutubeChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class YoutubeComparisonController extends Controller
{
    /**
     * Show comparison page
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get user's channels for selection
        $query = YoutubeChannel::active();
        
        if (!$user->hasRole('admin')) {
            $query->forUser($user->id);
        }
        
        $channels = $query->with(['snapshots' => function($q) {
            $q->latest('snapshot_date')->limit(30);
        }])->get();

        // Get selected channels for comparison
        $selectedChannels = collect();
        if ($request->filled('channels')) {
            $channelIds = explode(',', $request->get('channels'));
            $selectedChannels = $channels->whereIn('id', $channelIds)->take(5); // Max 5 channels
        }

        return view('youtube-monitoring.comparison', compact('channels', 'selectedChannels'));
    }

    /**
     * Get comparison data via API
     */
    public function compare(Request $request)
    {
        $request->validate([
            'channel_ids' => 'required|array|min:2|max:5',
            'channel_ids.*' => 'exists:youtube_channels,id'
        ]);

        $user = Auth::user();
        $channelIds = $request->get('channel_ids');

        // Get channels with permission check
        $query = YoutubeChannel::whereIn('id', $channelIds)
            ->with(['snapshots' => function($q) {
                $q->latest('snapshot_date')->limit(30);
            }]);

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

        // Prepare comparison data
        $comparisonData = [
            'channels' => [],
            'metrics' => $this->getComparisonMetrics($channels),
            'charts' => $this->getChartData($channels),
            'insights' => $this->generateInsights($channels)
        ];

        foreach ($channels as $channel) {
            $latest = $channel->latestSnapshot();
            $growth = $channel->getGrowthMetrics();
            
            $comparisonData['channels'][] = [
                'id' => $channel->id,
                'name' => $channel->channel_name,
                'thumbnail' => $channel->thumbnail_url,
                'url' => $channel->channel_url,
                'subscribers' => $latest ? $latest->subscriber_count : 0,
                'videos' => $latest ? $latest->video_count : 0,
                'views' => $latest ? $latest->view_count : 0,
                'growth' => $growth,
                'created_at' => $channel->channel_created_at,
                'last_synced' => $channel->last_synced_at
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $comparisonData
        ]);
    }

    /**
     * Generate comprehensive comparison metrics
     */
    private function getComparisonMetrics($channels): array
    {
        $metrics = [
            'tong_ket_co_ban' => [
                'tong_subscribers' => 0,
                'tong_videos' => 0,
                'tong_luot_xem' => 0,
                'tb_subscribers' => 0,
                'tb_videos' => 0,
                'tb_luot_xem' => 0,
            ],
            'top_kenh' => [
                'kenh_nhieu_sub_nhat' => null,
                'kenh_tang_truong_nhanh_nhat' => null,
                'kenh_tuong_tac_cao_nhat' => null,
                'kenh_san_xuat_nhieu_nhat' => null,
                'kenh_hieu_qua_cao_nhat' => null,
            ],
            'phan_tich_canh_tranh' => [
                'ty_le_thi_phan' => [],
                'xep_hang_tang_truong' => [],
                'xep_hang_tuong_tac' => [],
                'xep_hang_san_xuat' => [],
            ],
            'chi_so_nang_cao' => [
                'tb_ty_le_tuong_tac' => 0,
                'tb_hieu_qua_subscriber' => 0,
                'tb_toc_do_san_xuat' => 0,
                'tong_doanh_thu_hang_thang' => 0,
            ]
        ];

        $channelMetrics = [];
        $totalSubscribers = 0;
        $aiAnalysisCount = 0;

        // Calculate metrics for each channel
        foreach ($channels as $channel) {
            $latest = $channel->latestSnapshot();
            $growth = $channel->getGrowthMetrics();

            if (!$latest) continue;

            // Basic metrics
            $subscribers = $latest->subscriber_count;
            $videos = $latest->video_count;
            $views = $latest->view_count;

            // Tính toán nâng cao
            $tuoiKenh = $channel->channel_created_at ?
                $channel->channel_created_at->diffInDays(now()) : 1;

            $hieuQuaSubscriber = $subscribers > 0 ? round($views / $subscribers, 2) : 0;
            $tocDoSanXuat = $tuoiKenh > 0 ? round($videos / $tuoiKenh * 365, 2) : 0; // videos/năm
            $tbLuotXemMoiVideo = $videos > 0 ? round($views / $videos) : 0;

            // Tỷ lệ tương tác (tính từ 10 video gần nhất)
            $videoGanDay = $channel->videos()->with('snapshots')->limit(10)->get();
            $tongTuongTac = 0;
            $tongLuotXemVideo = 0;

            foreach ($videoGanDay as $video) {
                $videoSnapshot = $video->latestSnapshot();
                if ($videoSnapshot) {
                    $luotXemVideo = $videoSnapshot->view_count;
                    $tuongTacVideo = $videoSnapshot->like_count + $videoSnapshot->comment_count;
                    $tongTuongTac += $tuongTacVideo;
                    $tongLuotXemVideo += $luotXemVideo;
                }
            }

            $tyLeTuongTac = $tongLuotXemVideo > 0 ?
                round(($tongTuongTac / $tongLuotXemVideo) * 100, 3) : 0;

            // Luôn dùng AI RPM - tự động chạy AI Analysis nếu chưa có
            $existingRpm = $this->getAiRpmForChannel($channel);
            if ($existingRpm === null) {
                $aiAnalysisCount++;
            }
            $rpmThucTe = $this->ensureAiRpmForChannel($channel);
            $rpmSource = 'ai_analysis';
            $chuDeKenh = $this->phanLoaiChuDeKenh($channel);

            // Tính doanh thu hàng tháng từ views hàng tháng và RPM (đã bao gồm 55% revenue share)
            $viewsHangThang = $this->estimateMonthlyViews($channel, $views, $videos, $tuoiKenh);
            $doanhThuHangThang = round($viewsHangThang * ($rpmThucTe / 1000));

            $channelMetrics[$channel->id] = [
                'kenh' => $channel,
                'subscribers' => $subscribers,
                'videos' => $videos,
                'luot_xem' => $views,
                'ty_le_tang_truong' => $growth['growth_rate'],
                'hieu_qua_subscriber' => $hieuQuaSubscriber,
                'toc_do_san_xuat' => $tocDoSanXuat,
                'ty_le_tuong_tac' => $tyLeTuongTac,
                'tb_luot_xem_moi_video' => $tbLuotXemMoiVideo,
                'doanh_thu_hang_thang' => $doanhThuHangThang,
                'views_hang_thang' => $viewsHangThang,
                'tuoi_kenh_ngay' => $tuoiKenh,
                'chu_de_kenh' => $chuDeKenh,
                'ten_chu_de' => $this->getTenChuDe($chuDeKenh),
                'rpm_thuc_te' => round($rpmThucTe, 2),
                'rpm_source' => $rpmSource,
            ];

            // Cộng vào tổng
            $metrics['tong_ket_co_ban']['tong_subscribers'] += $subscribers;
            $metrics['tong_ket_co_ban']['tong_videos'] += $videos;
            $metrics['tong_ket_co_ban']['tong_luot_xem'] += $views;
            $metrics['chi_so_nang_cao']['tong_doanh_thu_hang_thang'] += $doanhThuHangThang;
            $totalSubscribers += $subscribers;
        }

        $count = count($channelMetrics);
        if ($count > 0) {
            // Tính trung bình
            $metrics['tong_ket_co_ban']['tb_subscribers'] = $metrics['tong_ket_co_ban']['tong_subscribers'] / $count;
            $metrics['tong_ket_co_ban']['tb_videos'] = $metrics['tong_ket_co_ban']['tong_videos'] / $count;
            $metrics['tong_ket_co_ban']['tb_luot_xem'] = $metrics['tong_ket_co_ban']['tong_luot_xem'] / $count;

            $metrics['chi_so_nang_cao']['tb_ty_le_tuong_tac'] =
                array_sum(array_column($channelMetrics, 'ty_le_tuong_tac')) / $count;
            $metrics['chi_so_nang_cao']['tb_hieu_qua_subscriber'] =
                array_sum(array_column($channelMetrics, 'hieu_qua_subscriber')) / $count;
            $metrics['chi_so_nang_cao']['tb_toc_do_san_xuat'] =
                array_sum(array_column($channelMetrics, 'toc_do_san_xuat')) / $count;

            // Tìm kênh dẫn đầu
            $metrics['top_kenh']['kenh_nhieu_sub_nhat'] =
                collect($channelMetrics)->sortByDesc('subscribers')->first()['kenh'];
            $metrics['top_kenh']['kenh_tang_truong_nhanh_nhat'] =
                collect($channelMetrics)->sortByDesc('ty_le_tang_truong')->first()['kenh'];
            $metrics['top_kenh']['kenh_tuong_tac_cao_nhat'] =
                collect($channelMetrics)->sortByDesc('ty_le_tuong_tac')->first()['kenh'];
            $metrics['top_kenh']['kenh_san_xuat_nhieu_nhat'] =
                collect($channelMetrics)->sortByDesc('toc_do_san_xuat')->first()['kenh'];
            $metrics['top_kenh']['kenh_hieu_qua_cao_nhat'] =
                collect($channelMetrics)->sortByDesc('hieu_qua_subscriber')->first()['kenh'];

            // Tính thị phần và xếp hạng
            foreach ($channelMetrics as $id => $data) {
                $thiPhan = $totalSubscribers > 0 ?
                    round(($data['subscribers'] / $totalSubscribers) * 100, 2) : 0;
                $metrics['phan_tich_canh_tranh']['ty_le_thi_phan'][$id] = $thiPhan;
            }

            // Tạo bảng xếp hạng
            $metrics['phan_tich_canh_tranh']['xep_hang_tang_truong'] =
                collect($channelMetrics)->sortByDesc('ty_le_tang_truong')->keys()->toArray();
            $metrics['phan_tich_canh_tranh']['xep_hang_tuong_tac'] =
                collect($channelMetrics)->sortByDesc('ty_le_tuong_tac')->keys()->toArray();
            $metrics['phan_tich_canh_tranh']['xep_hang_san_xuat'] =
                collect($channelMetrics)->sortByDesc('toc_do_san_xuat')->keys()->toArray();
        }

        $metrics['channel_metrics'] = $channelMetrics;
        $metrics['ai_analysis_info'] = [
            'channels_analyzed' => $aiAnalysisCount,
            'total_channels' => count($channels),
            'all_using_ai' => $aiAnalysisCount === 0 // true nếu tất cả đã có AI analysis
        ];

        return $metrics;
    }

    /**
     * Generate chart data for comparison
     */
    private function getChartData($channels): array
    {
        $chartData = [
            'subscriber_comparison' => [],
            'growth_trends' => [],
            'video_performance' => []
        ];

        foreach ($channels as $channel) {
            $snapshots = $channel->snapshots->sortBy('snapshot_date');
            
            $chartData['subscriber_comparison'][] = [
                'name' => $channel->channel_name,
                'data' => $snapshots->pluck('subscriber_count')->toArray(),
                'dates' => $snapshots->pluck('snapshot_date')->map(function($date) {
                    return $date->format('M d');
                })->toArray()
            ];

            // Calculate daily growth rates
            $growthData = [];
            $previousCount = null;
            
            foreach ($snapshots as $snapshot) {
                if ($previousCount !== null && $previousCount > 0) {
                    $growthRate = (($snapshot->subscriber_count - $previousCount) / $previousCount) * 100;
                    $growthData[] = round($growthRate, 2);
                } else {
                    $growthData[] = 0;
                }
                $previousCount = $snapshot->subscriber_count;
            }

            $chartData['growth_trends'][] = [
                'name' => $channel->channel_name,
                'data' => $growthData
            ];
        }

        return $chartData;
    }

    /**
     * Generate insights from comparison
     */
    private function generateInsights($channels): array
    {
        $insights = [];
        
        // Performance insights
        $latest = $channels->map(function($channel) {
            return [
                'channel' => $channel,
                'snapshot' => $channel->latestSnapshot(),
                'growth' => $channel->getGrowthMetrics()
            ];
        })->filter(function($item) {
            return $item['snapshot'] !== null;
        });

        if ($latest->count() >= 2) {
            // Subscriber comparison
            $sorted = $latest->sortByDesc('snapshot.subscriber_count');
            $leader = $sorted->first();
            $second = $sorted->skip(1)->first();
            
            $gap = $leader['snapshot']->subscriber_count - $second['snapshot']->subscriber_count;
            $insights[] = [
                'type' => 'leader',
                'title' => 'Kênh dẫn đầu',
                'message' => "{$leader['channel']->channel_name} dẫn đầu với " . 
                           number_format($leader['snapshot']->subscriber_count) . " subscribers, " .
                           "hơn {$second['channel']->channel_name} " . number_format($gap) . " subscribers."
            ];

            // Growth comparison
            $fastestGrowing = $latest->sortByDesc('growth.growth_rate')->first();
            if ($fastestGrowing['growth']['growth_rate'] > 0) {
                $insights[] = [
                    'type' => 'growth',
                    'title' => 'Tăng trưởng nhanh nhất',
                    'message' => "{$fastestGrowing['channel']->channel_name} có tốc độ tăng trưởng cao nhất: " .
                               number_format($fastestGrowing['growth']['growth_rate'], 2) . "% trong 24h."
                ];
            }

            // Content volume comparison
            $mostActive = $latest->sortByDesc('snapshot.video_count')->first();
            $insights[] = [
                'type' => 'content',
                'title' => 'Kênh sản xuất nhiều nhất',
                'message' => "{$mostActive['channel']->channel_name} có nhiều video nhất với " .
                           number_format($mostActive['snapshot']->video_count) . " videos."
            ];
        }

        return $insights;
    }

    /**
     * Phân loại chủ đề kênh dựa trên tên và mô tả
     */
    private function phanLoaiChuDeKenh($channel): string
    {
        $tenKenh = strtolower($channel->channel_name);
        $moTa = strtolower($channel->description ?? '');
        $noiDung = $tenKenh . ' ' . $moTa;

        // Từ khóa cho từng chủ đề
        $chuDeMap = [
            'tai_chinh' => [
                'đầu tư', 'tài chính', 'chứng khoán', 'forex', 'crypto', 'bitcoin', 'kinh doanh',
                'khởi nghiệp', 'làm giàu', 'tiền', 'bank', 'ngân hàng', 'bất động sản', 'investment',
                'trading', 'finance', 'money', 'rich', 'wealth', 'business'
            ],
            'cong_nghe' => [
                'công nghệ', 'tech', 'phần mềm', 'lập trình', 'coding', 'ai', 'smartphone',
                'laptop', 'review', 'unboxing', 'technology', 'software', 'hardware', 'app',
                'website', 'digital', 'internet', 'online', 'programming', 'developer'
            ],
            'giao_duc' => [
                'giáo dục', 'học', 'dạy', 'kỹ năng', 'kiến thức', 'education', 'learning',
                'tutorial', 'hướng dẫn', 'course', 'skill', 'tips', 'how to', 'study',
                'university', 'school', 'teacher', 'lesson', 'training'
            ],
            'giai_tri' => [
                'giải trí', 'vlog', 'travel', 'du lịch', 'ăn uống', 'food', 'funny', 'hài',
                'entertainment', 'lifestyle', 'daily', 'cuộc sống', 'review', 'reaction',
                'challenge', 'prank', 'comedy', 'fun', 'show'
            ],
            'game' => [
                'game', 'gaming', 'play', 'chơi game', 'stream', 'esports', 'mobile legends',
                'pubg', 'lol', 'fifa', 'minecraft', 'roblox', 'free fire', 'valorant',
                'gameplay', 'walkthrough', 'guide game'
            ],
            'tre_em' => [
                'trẻ em', 'kids', 'children', 'baby', 'toy', 'đồ chơi', 'cartoon', 'hoạt hình',
                'nursery', 'family', 'gia đình', 'mẹ và bé', 'parenting', 'education kids'
            ],
            'nhac' => [
                'nhạc', 'music', 'song', 'bài hát', 'ca sĩ', 'singer', 'band', 'cover',
                'karaoke', 'mv', 'music video', 'acoustic', 'live', 'concert', 'album'
            ]
        ];

        // Tìm chủ đề phù hợp nhất
        $diemSo = [];
        foreach ($chuDeMap as $chuDe => $tuKhoa) {
            $diem = 0;
            foreach ($tuKhoa as $tu) {
                if (strpos($noiDung, $tu) !== false) {
                    $diem++;
                }
            }
            $diemSo[$chuDe] = $diem;
        }

        // Trả về chủ đề có điểm cao nhất, mặc định là giải trí
        $chuDeCoNhieuDiemNhat = array_keys($diemSo, max($diemSo))[0];
        return max($diemSo) > 0 ? $chuDeCoNhieuDiemNhat : 'giai_tri';
    }

    /**
     * Lấy RPM theo chủ đề (USD per 1000 views - đã bao gồm 55% revenue share)
     */
    private function getRpmTheoChuDe(string $chuDe): float
    {
        // RPM đã tính sẵn 55% revenue share cho creator
        $rpmMap = [
            'tai_chinh' => 1.65,     // 3.0 * 0.55 = 1.65
            'cong_nghe' => 1.32,     // 2.4 * 0.55 = 1.32
            'giao_duc' => 1.02,      // 1.85 * 0.55 = 1.02
            'giai_tri' => 0.28,      // 0.5 * 0.55 = 0.28
            'game' => 0.28,          // 0.5 * 0.55 = 0.28
            'tre_em' => 0.22,        // 0.4 * 0.55 = 0.22
            'nhac' => 0.17,          // 0.3 * 0.55 = 0.17
        ];

        return $rpmMap[$chuDe] ?? 0.28; // Mặc định giải trí
    }

    /**
     * Lấy hệ số RPM theo tháng (mùa quảng cáo)
     */
    private function getRpmTheoThang(): float
    {
        $thangHienTai = now()->month;

        $heSoThang = [
            1 => 0.7,   // Tháng 1: Thấp sau Tết
            2 => 0.8,   // Tháng 2: Thấp
            3 => 1.0,   // Tháng 3: Bình thường
            4 => 1.0,   // Tháng 4: Bình thường
            5 => 1.1,   // Tháng 5: Hơi cao
            6 => 1.0,   // Tháng 6: Bình thường
            7 => 1.0,   // Tháng 7: Bình thường
            8 => 1.1,   // Tháng 8: Hơi cao
            9 => 1.2,   // Tháng 9: Cao (back to school)
            10 => 1.3,  // Tháng 10: Cao (chuẩn bị cuối năm)
            11 => 1.8,  // Tháng 11: Rất cao (Black Friday, chuẩn bị Tết)
            12 => 2.0,  // Tháng 12: Cao nhất (mùa Noel, cuối năm)
        ];

        return $heSoThang[$thangHienTai] ?? 1.0;
    }

    /**
     * Lấy tên chủ đề tiếng Việt
     */
    private function getTenChuDe(string $chuDe): string
    {
        $tenMap = [
            'tai_chinh' => 'Tài chính & Đầu tư',
            'cong_nghe' => 'Công nghệ & Phần mềm',
            'giao_duc' => 'Giáo dục & Kỹ năng',
            'giai_tri' => 'Giải trí & Vlog',
            'game' => 'Game & Esports',
            'tre_em' => 'Nội dung trẻ em',
            'nhac' => 'Âm nhạc',
        ];

        return $tenMap[$chuDe] ?? 'Giải trí & Vlog';
    }

    /**
     * Đảm bảo kênh có AI RPM - tự động chạy AI Analysis nếu cần
     */
    private function ensureAiRpmForChannel($channel): float
    {
        // Kiểm tra AI analysis gần đây (trong 7 ngày)
        $existingRpm = $this->getAiRpmForChannel($channel);

        if ($existingRpm !== null) {
            return $existingRpm;
        }

        // Chưa có AI analysis hoặc đã cũ → Chạy AI Analysis mới
        Log::info('Running AI Analysis for channel comparison', [
            'channel_id' => $channel->id,
            'channel_name' => $channel->channel_name
        ]);

        try {
            $aiService = new \App\Services\YoutubeAIAnalysisService();
            $result = $aiService->analyzeChannel($channel);

            if ($result['success']) {
                // Extract RPM từ kết quả AI
                $newRpm = $aiService->extractCpmFromResponse($result['analysis']);

                if ($newRpm > 0) {
                    // Lưu vào database
                    \DB::table('youtube_ai_analysis')->insert([
                        'youtube_channel_id' => $channel->id,
                        'analysis_content' => $result['analysis'],
                        'extracted_cpm' => $newRpm,
                        'cpm_source' => 'ai_comparison',
                        'analysis_metadata' => json_encode([
                            'triggered_by' => 'channel_comparison',
                            'timestamp' => now()
                        ]),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    Log::info('AI Analysis completed for comparison', [
                        'channel_id' => $channel->id,
                        'extracted_rpm' => $newRpm
                    ]);

                    return $newRpm;
                }
            }

            // AI Analysis thất bại → Dùng fallback
            Log::warning('AI Analysis failed for comparison, using fallback', [
                'channel_id' => $channel->id,
                'error' => $result['error'] ?? 'Unknown error'
            ]);

            return $this->getFallbackRpm($channel);

        } catch (\Exception $e) {
            Log::error('Exception during AI Analysis for comparison', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage()
            ]);

            return $this->getFallbackRpm($channel);
        }
    }

    /**
     * Lấy RPM từ AI analysis gần nhất của kênh
     */
    private function getAiRpmForChannel($channel): ?float
    {
        try {
            // Tìm AI analysis gần nhất trong 7 ngày
            $recentAnalysis = \DB::table('youtube_ai_analysis')
                ->where('youtube_channel_id', $channel->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$recentAnalysis) {
                return null;
            }

            // Extract RPM từ analysis content
            $aiService = new \App\Services\YoutubeAIAnalysisService();
            $rpm = $aiService->extractCpmFromResponse($recentAnalysis->analysis_content);

            if ($rpm > 0) {
                Log::info('Using AI RPM for channel comparison', [
                    'channel_id' => $channel->id,
                    'ai_rpm' => $rpm,
                    'analysis_date' => $recentAnalysis->created_at
                ]);
                return $rpm;
            }

            return null;

        } catch (\Exception $e) {
            Log::warning('Failed to get AI RPM for channel', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Fallback RPM khi AI Analysis thất bại
     */
    private function getFallbackRpm($channel): float
    {
        $chuDeKenh = $this->phanLoaiChuDeKenh($channel);
        $rpmTheoThang = $this->getRpmTheoThang();
        $rpmCoBan = $this->getRpmTheoChuDe($chuDeKenh);

        Log::info('Using fallback RPM calculation', [
            'channel_id' => $channel->id,
            'category' => $chuDeKenh,
            'base_rpm' => $rpmCoBan,
            'month_factor' => $rpmTheoThang,
            'final_rpm' => $rpmCoBan * $rpmTheoThang
        ]);

        return $rpmCoBan * $rpmTheoThang;
    }

    /**
     * Ước tính views hàng tháng dựa trên hoạt động gần đây
     */
    private function estimateMonthlyViews($channel, $totalViews, $totalVideos, $channelAgeInDays): int
    {
        // Lấy views từ 30 ngày gần nhất nếu có snapshots
        $recentViews = $this->getRecentMonthlyViews($channel);

        if ($recentViews > 0) {
            return $recentViews;
        }

        // Fallback: Ước tính dựa trên tổng views và tuổi kênh
        if ($channelAgeInDays > 30) {
            $dailyViews = $totalViews / $channelAgeInDays;
            return round($dailyViews * 30); // 30 ngày
        }

        // Kênh mới: Dùng average views per video * estimated videos per month
        if ($totalVideos > 0) {
            $avgViewsPerVideo = $totalViews / $totalVideos;
            $videosPerMonth = ($totalVideos / max($channelAgeInDays, 1)) * 30;
            return round($avgViewsPerVideo * $videosPerMonth);
        }

        return 0;
    }

    /**
     * Lấy views thực tế từ 30 ngày gần nhất
     */
    private function getRecentMonthlyViews($channel): int
    {
        $snapshots = $channel->snapshots()
            ->where('snapshot_date', '>=', now()->subDays(30))
            ->orderBy('snapshot_date', 'asc')
            ->get();

        if ($snapshots->count() < 2) {
            return 0;
        }

        $firstSnapshot = $snapshots->first();
        $lastSnapshot = $snapshots->last();

        return max(0, $lastSnapshot->view_count - $firstSnapshot->view_count);
    }
}
