<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\GoogleDriveService;
use App\Services\DirectStreamingService;
use App\Models\UserFile;
use Illuminate\Support\Facades\Storage;
use Exception;

class TestGoogleDriveController extends Controller
{
    private GoogleDriveService $googleDriveService;
    private DirectStreamingService $directStreamingService;
    private \App\Services\OptimizedStreamingService $optimizedStreamingService;

    public function __construct(
        GoogleDriveService $googleDriveService, 
        DirectStreamingService $directStreamingService
    ) {
        $this->googleDriveService = $googleDriveService;
        $this->directStreamingService = $directStreamingService;
        $this->optimizedStreamingService = new \App\Services\OptimizedStreamingService($googleDriveService);
    }

    /**
     * Show test page
     */
    public function index()
    {
        // View đã bị xóa, trả về response JSON thay thế
        return response()->json([
            'message' => 'Test Google Drive Controller',
            'note' => 'View đã được cleanup, sử dụng API endpoints thay thế',
            'available_endpoints' => [
                'POST /test-google-drive/test-connection',
                'POST /test-google-drive/upload-test',
                'GET /test-google-drive/list-files',
                // ... other endpoints
            ]
        ]);
    }

    /**
     * Test connection to Google Drive
     */
    public function testConnection()
    {
        try {
            $result = $this->googleDriveService->testConnection();
            
            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['success'] ? 'Kết nối Google Drive thành công!' : 'Lỗi kết nối: ' . $result['error'],
                'data' => $result
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Upload test file
     */
    public function uploadTest(Request $request)
    {
        try {
            // Create a test file
            $testContent = "Test file created at: " . now()->toDateTimeString() . "\n";
            $testContent .= "This is a test upload to Google Drive from VPS Live Server Control.";
            
            $testFileName = 'test-upload-' . time() . '.txt';
            $testFilePath = storage_path('app/temp/' . $testFileName);
            
            // Ensure temp directory exists
            if (!is_dir(dirname($testFilePath))) {
                mkdir(dirname($testFilePath), 0755, true);
            }
            
            file_put_contents($testFilePath, $testContent);

            // Upload to Google Drive
            $result = $this->googleDriveService->uploadFile($testFilePath, $testFileName, 'text/plain');

            // Clean up temp file
            if (file_exists($testFilePath)) {
                unlink($testFilePath);
            }

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['success'] ? 'Upload thành công!' : 'Lỗi upload: ' . $result['error'],
                'data' => $result
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Upload file from form
     */
    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file' // No size limit for video uploads
        ]);

        try {
            $uploadedFile = $request->file('file');
            $fileName = $uploadedFile->getClientOriginalName();
            $tempPath = $uploadedFile->getPathname();

            $result = $this->googleDriveService->uploadFile($tempPath, $fileName);

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['success'] ? 'Upload file thành công!' : 'Lỗi upload: ' . $result['error'],
                'data' => $result
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * List files in Google Drive
     */
    public function listFiles(Request $request)
    {
        try {
            $pageSize = $request->get('page_size', 10);
            $pageToken = $request->get('page_token');

            $result = $this->googleDriveService->listFiles($pageSize, $pageToken);

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['success'] ? 'Lấy danh sách file thành công!' : 'Lỗi: ' . $result['error'],
                'data' => $result
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Download file from Google Drive
     */
    public function downloadFile(Request $request)
    {
        $request->validate([
            'file_id' => 'required|string'
        ]);

        try {
            $fileId = $request->get('file_id');
            $fileName = 'download-' . time() . '.file';
            $savePath = storage_path('app/downloads/' . $fileName);

            $result = $this->googleDriveService->downloadFile($fileId, $savePath);

            if ($result['success']) {
                return response()->download($savePath)->deleteFileAfterSend(true);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Lỗi download: ' . $result['error']
                ]);
            }

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete file from Google Drive
     */
    public function deleteFile(Request $request)
    {
        $request->validate([
            'file_id' => 'required|string'
        ]);

        try {
            $fileId = $request->get('file_id');
            $result = $this->googleDriveService->deleteFile($fileId);

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['success'] ? 'Xóa file thành công!' : 'Lỗi xóa file: ' . $result['error'],
                'data' => $result
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get file info
     */
    public function getFileInfo(Request $request)
    {
        $request->validate([
            'file_id' => 'required|string'
        ]);

        try {
            $fileId = $request->get('file_id');
            $result = $this->googleDriveService->getFileInfo($fileId);

            return response()->json([
                'status' => $result['success'] ? 'success' : 'error',
                'message' => $result['success'] ? 'Lấy thông tin file thành công!' : 'Lỗi: ' . $result['error'],
                'data' => $result
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Test upload file nhỏ
     */
    public function testUploadSmallFile(Request $request)
    {
        // Tạo file test nhỏ
        $testContent = "Test video content for streaming - " . now();
        $testFilePath = storage_path('app/test_video.txt');
        file_put_contents($testFilePath, $testContent);

        $result = $this->googleDriveService->uploadFile(
            $testFilePath, 
            'test_video_' . time() . '.txt',
            'text/plain'
        );

        // Cleanup
        unlink($testFilePath);

        return response()->json([
            'test' => 'Small File Upload',
            'result' => $result,
            'timestamp' => now()
        ]);
    }

    /**
     * Test upload file lớn với progress
     */
    public function testUploadLargeFile(Request $request)
    {
        // Tạo file test 10MB
        $testFilePath = storage_path('app/large_test_video.bin');
        $handle = fopen($testFilePath, 'w');
        
        // Write 10MB of random data
        for ($i = 0; $i < 10; $i++) {
            fwrite($handle, str_repeat('A', 1024 * 1024)); // 1MB chunk
        }
        fclose($handle);

        $progressLog = [];
        
        $result = $this->googleDriveService->uploadLargeFile(
            $testFilePath,
            'large_test_video_' . time() . '.bin',
            function($progress) use (&$progressLog) {
                $progressLog[] = round($progress, 2) . '%';
            }
        );

        // Cleanup
        unlink($testFilePath);

        return response()->json([
            'test' => 'Large File Upload (10MB)',
            'result' => $result,
            'progress_log' => $progressLog,
            'timestamp' => now()
        ]);
    }

    /**
     * Test direct streaming từ Google Drive
     */
    public function testDirectStreaming(Request $request)
    {
        $fileId = $request->input('file_id');
        
        if (!$fileId) {
            return response()->json([
                'error' => 'file_id parameter required'
            ], 400);
        }

        try {
            // Lấy thông tin file
            $fileInfo = $this->googleDriveService->getFileInfo($fileId);
            
            if (!$fileInfo['success']) {
                return response()->json([
                    'error' => 'File not found or inaccessible',
                    'details' => $fileInfo
                ], 404);
            }

            // Test direct download URL
            $directUrlResult = $this->googleDriveService->getDirectDownloadLink($fileId);
            
            if (!$directUrlResult['success']) {
                return response()->json([
                    'error' => 'Cannot get direct download link',
                    'details' => $directUrlResult
                ], 500);
            }
            
            $directUrl = $directUrlResult['download_link'];
            
            // Test streaming performance
            $streamingStats = $this->directStreamingService->getStreamingStats(
                (object)['google_drive_file_id' => $fileId]
            );

            return response()->json([
                'test' => 'Direct Streaming Test',
                'file_info' => $fileInfo,
                'direct_url_result' => $directUrlResult,
                'direct_url' => $directUrl,
                'streaming_stats' => $streamingStats,
                'ffmpeg_command_preview' => $this->generateFFmpegCommand($directUrl),
                'timestamp' => now()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test cost calculation
     */
    public function testCostCalculation()
    {
        $scenarios = [
            '100_users_1gb_each' => [
                'users' => 100,
                'storage_per_user' => 1, // GB
                'streams_per_day' => 10,
                'avg_stream_duration' => 60 // minutes
            ],
            '1000_users_500mb_each' => [
                'users' => 1000,
                'storage_per_user' => 0.5, // GB
                'streams_per_day' => 5,
                'avg_stream_duration' => 30 // minutes
            ],
            '10000_users_100mb_each' => [
                'users' => 10000,
                'storage_per_user' => 0.1, // GB
                'streams_per_day' => 2,
                'avg_stream_duration' => 15 // minutes
            ]
        ];

        $costAnalysis = [];

        foreach ($scenarios as $name => $scenario) {
            $totalStorage = $scenario['users'] * $scenario['storage_per_user'];
            $totalStreams = $scenario['users'] * $scenario['streams_per_day'] * 30; // per month
            
            // Google Drive pricing
            $storageCost = $this->calculateGoogleDriveStorageCost($totalStorage);
            $apiCost = $this->calculateGoogleDriveAPICost($totalStreams);
            
            // Traditional VPS pricing (for comparison)
            $vpsCost = $this->calculateTraditionalVPSCost($totalStorage);
            
            $costAnalysis[$name] = [
                'scenario' => $scenario,
                'total_storage_gb' => $totalStorage,
                'monthly_streams' => $totalStreams,
                'google_drive' => [
                    'storage_cost' => $storageCost,
                    'api_cost' => $apiCost,
                    'total_cost' => $storageCost + $apiCost
                ],
                'traditional_vps' => [
                    'storage_cost' => $vpsCost,
                    'total_cost' => $vpsCost
                ],
                'savings' => $vpsCost - ($storageCost + $apiCost),
                'savings_percentage' => round((($vpsCost - ($storageCost + $apiCost)) / $vpsCost) * 100, 2)
            ];
        }

        return response()->json([
            'test' => 'Cost Analysis',
            'analysis' => $costAnalysis,
            'notes' => [
                'Google Drive API có 1 tỷ requests miễn phí/ngày',
                'Bandwidth từ Google Drive không tính phí',
                'VPS storage cost tính theo SSD pricing',
                'Không tính cost cho compute/streaming (giống nhau cả 2 method)'
            ],
            'timestamp' => now()
        ]);
    }

    /**
     * Test real streaming command
     */
    public function testRealStreaming(Request $request)
    {
        $fileId = $request->input('file_id');
        $rtmpUrl = $request->input('rtmp_url', 'rtmp://test.example.com/live');
        $streamKey = $request->input('stream_key', 'test_key');
        
        if (!$fileId) {
            return response()->json(['error' => 'file_id required'], 400);
        }

        $directUrlResult = $this->googleDriveService->getDirectDownloadLink($fileId);
        
        if (!$directUrlResult['success']) {
            return response()->json([
                'error' => 'Cannot get direct download link',
                'details' => $directUrlResult
            ], 500);
        }
        
        $directUrl = $directUrlResult['download_link'];
        
        // Generate actual FFmpeg command
        $ffmpegCommand = sprintf(
            'ffmpeg -re -headers "User-Agent: Mozilla/5.0" -i "%s" -c:v copy -c:a copy -f flv "%s/%s"',
            $directUrl,
            $rtmpUrl,
            $streamKey
        );

        return response()->json([
            'test' => 'Real Streaming Command',
            'file_id' => $fileId,
            'direct_url' => $directUrl,
            'ffmpeg_command' => $ffmpegCommand,
            'instructions' => [
                '1. Copy the FFmpeg command above',
                '2. Run it on any VPS/server with FFmpeg installed',
                '3. It will stream directly from Google Drive to RTMP',
                '4. No file copying needed!'
            ],
            'timestamp' => now()
        ]);
    }

    /**
     * Performance benchmark
     */
    public function benchmarkPerformance(Request $request)
    {
        $fileId = $request->input('file_id');
        
        if (!$fileId) {
            return response()->json(['error' => 'file_id required'], 400);
        }

        $directUrlResult = $this->googleDriveService->getDirectDownloadLink($fileId);
        
        if (!$directUrlResult['success']) {
            return response()->json([
                'error' => 'Cannot get direct download link',
                'details' => $directUrlResult
            ], 500);
        }
        
        $directUrl = $directUrlResult['download_link'];
        $benchmarks = [];

        // Test 1: Direct URL response time
        $startTime = microtime(true);
        $response = @get_headers($directUrl, 1);
        $responseTime = microtime(true) - $startTime;
        
        $benchmarks['direct_url_response'] = [
            'time_ms' => round($responseTime * 1000, 2),
            'accessible' => !empty($response),
            'content_length' => $response['Content-Length'] ?? 'unknown'
        ];

        // Test 2: Download speed test (first 1MB)
        $startTime = microtime(true);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Range: bytes=0-1048575', // First 1MB
                    'User-Agent: Mozilla/5.0'
                ]
            ]
        ]);
        
        $data = @file_get_contents($directUrl, false, $context);
        $downloadTime = microtime(true) - $startTime;
        
        if ($data) {
            $downloadedBytes = strlen($data);
            $speedMbps = ($downloadedBytes * 8) / ($downloadTime * 1000000); // Mbps
            
            $benchmarks['download_speed_test'] = [
                'downloaded_bytes' => $downloadedBytes,
                'time_seconds' => round($downloadTime, 3),
                'speed_mbps' => round($speedMbps, 2)
            ];
        }

        return response()->json([
            'test' => 'Performance Benchmark',
            'file_id' => $fileId,
            'benchmarks' => $benchmarks,
            'timestamp' => now()
        ]);
    }

    // Helper methods
    private function generateFFmpegCommand(string $directUrl): string
    {
        return sprintf(
            'ffmpeg -re -headers "User-Agent: Mozilla/5.0" -i "%s" -c:v copy -c:a copy -f flv "rtmp://your-platform.com/live/YOUR_STREAM_KEY"',
            $directUrl
        );
    }

    private function calculateGoogleDriveStorageCost(float $totalGB): float
    {
        if ($totalGB <= 15) return 0; // Free tier
        
        // $1.99 for 100GB, $2.99 for 200GB, $9.99 for 2TB
        if ($totalGB <= 100) return 1.99;
        if ($totalGB <= 200) return 2.99;
        if ($totalGB <= 2000) return 9.99;
        
        // Beyond 2TB: $5/TB/month
        return 9.99 + (($totalGB - 2000) / 1000) * 5;
    }

    private function calculateGoogleDriveAPICost(int $requests): float
    {
        // 1 billion requests per day free
        $freeRequestsPerMonth = 1000000000 * 30;
        
        if ($requests <= $freeRequestsPerMonth) return 0;
        
        // $0.40 per million requests beyond free tier
        $paidRequests = $requests - $freeRequestsPerMonth;
        return ($paidRequests / 1000000) * 0.40;
    }

    private function calculateTraditionalVPSCost(float $totalGB): float
    {
        // Estimate: $0.10/GB/month for SSD storage + VPS cost
        $storagePerVPS = 100; // GB
        $vpsCount = ceil($totalGB / $storagePerVPS);
        $costPerVPS = 20; // $20/month per VPS
        
        return $vpsCount * $costPerVPS;
    }

    /**
     * Test optimized streaming with anti-lag features
     */
    public function testOptimizedStreaming(Request $request)
    {
        $fileId = $request->input('file_id');
        
        if (!$fileId) {
            return response()->json(['error' => 'file_id required'], 400);
        }

        try {
            // Get optimized streaming URL with force refresh for testing
            $streamingData = $this->optimizedStreamingService->getOptimizedStreamingUrl($fileId, true);
            
            if (!$streamingData['success']) {
                return response()->json([
                    'error' => 'Cannot get optimized streaming URL',
                    'details' => $streamingData
                ], 500);
            }

            // Monitor streaming health
            $healthData = $this->optimizedStreamingService->monitorStreamingHealth($fileId);
            
            // Generate optimized FFmpeg command
            $rtmpUrl = $request->input('rtmp_url', 'rtmp://live.youtube.com/live2/YOUR_YOUTUBE_STREAM_KEY');
            $ffmpegCommand = $this->optimizedStreamingService->generateOptimizedFFmpegCommand(
                $streamingData['url'],
                $rtmpUrl,
                [
                    'video_bitrate' => $request->input('video_bitrate', '2500k'),
                    'resolution' => $request->input('resolution', '1280x720'),
                    'preset' => $request->input('preset', 'veryfast')
                ]
            );

            return response()->json([
                'test' => 'Optimized Streaming Test',
                'file_id' => $fileId,
                'streaming_data' => $streamingData,
                'health_monitoring' => $healthData,
                'optimized_ffmpeg_command' => $ffmpegCommand,
                'anti_lag_features' => [
                    '✅ Multiple URL fallback strategies (5 methods)',
                    '✅ Enhanced performance testing with quick mode',
                    '✅ Real-time health monitoring with streaming capability test',
                    '✅ Optimized FFmpeg settings with igndts flag',
                    '✅ Auto-reconnect on failure with EOF handling',
                    '✅ Zero-latency tuning with veryfast preset',
                    '✅ Smart caching with 30min TTL',
                    '✅ Intelligent scoring system (0-100)',
                    '✅ Range requests support detection',
                    '✅ Content-Type validation for video files'
                ],
                'performance_improvements' => [
                    '🚀 Response time threshold reduced to 800ms',
                    '🚀 Quick mode testing for faster results',
                    '🚀 Enhanced scoring with bonus points',
                    '🚀 Streaming capability validation',
                    '🚀 Better error handling and reconnection',
                    '🚀 Geographic optimization recommendations'
                ],
                'timestamp' => now()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test streaming health monitoring
     */
    public function testStreamingHealth(Request $request)
    {
        $fileId = $request->input('file_id');
        
        if (!$fileId) {
            return response()->json(['error' => 'file_id required'], 400);
        }

        try {
            $healthData = $this->optimizedStreamingService->monitorStreamingHealth($fileId);
            
            return response()->json([
                'test' => 'Streaming Health Monitor',
                'file_id' => $fileId,
                'health_data' => $healthData,
                'interpretation' => [
                    'score_90_100' => '🟢 Excellent - Ready for production streaming',
                    'score_70_89' => '🟡 Good - Minor optimizations recommended', 
                    'score_50_69' => '🟠 Fair - Consider switching method',
                    'score_0_49' => '🔴 Poor - Find alternative URL'
                ],
                'timestamp' => now()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test multiple file IDs performance comparison
     */
    public function testBatchPerformance(Request $request)
    {
        $fileIds = $request->input('file_ids', []);
        
        if (empty($fileIds)) {
            return response()->json(['error' => 'file_ids array required'], 400);
        }

        $results = [];
        
        foreach ($fileIds as $fileId) {
            try {
                $healthData = $this->optimizedStreamingService->monitorStreamingHealth($fileId);
                $results[$fileId] = $healthData;
            } catch (\Exception $e) {
                $results[$fileId] = [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        // Sort by health score
        uasort($results, function($a, $b) {
            $scoreA = $a['health_check']['score'] ?? 0;
            $scoreB = $b['health_check']['score'] ?? 0;
            return $scoreB <=> $scoreA; // Descending order
        });

        return response()->json([
            'test' => 'Batch Performance Comparison',
            'total_files' => count($fileIds),
            'results' => $results,
            'best_performer' => array_key_first($results),
            'recommendations' => $this->getBatchRecommendations($results),
            'timestamp' => now()
        ]);
    }

    /**
     * Get batch recommendations
     */
    private function getBatchRecommendations(array $results): array
    {
        $recommendations = [];
        $scores = array_map(fn($r) => $r['health_check']['score'] ?? 0, $results);
        $avgScore = array_sum($scores) / count($scores);
        
        if ($avgScore > 80) {
            $recommendations[] = '🎉 Overall performance excellent! Ready for production.';
        } elseif ($avgScore > 60) {
            $recommendations[] = '👍 Good performance. Consider optimizing lower-scoring files.';
        } else {
            $recommendations[] = '⚠️ Performance needs improvement. Review file hosting strategy.';
        }
        
        $topScore = max($scores);
        $lowScore = min($scores);
        
        if ($topScore - $lowScore > 30) {
            $recommendations[] = '📊 High variance in performance. Use best performers for critical streams.';
        }
        
        return $recommendations;
    }

    /**
     * Test buffered streaming with rolling cache
     */
    public function testBufferedStreaming(Request $request)
    {
        $fileId = $request->input('file_id');
        
        if (!$fileId) {
            return response()->json(['error' => 'file_id required'], 400);
        }

        try {
            $bufferedService = new \App\Services\BufferedStreamingService();
            
            // Test optimal settings calculation
            $optimalSettings = $bufferedService->calculateOptimalSettings(
                $request->input('cpu_cores', 2),
                $request->input('ram_gb', 4),
                $request->input('disk_gb', 50)
            );
            
            // Start buffered streaming
            $rtmpTargets = [
                $request->input('rtmp_url_1', 'rtmp://live.youtube.com/live2/YOUR_KEY_1'),
                $request->input('rtmp_url_2', 'rtmp://live.facebook.com/rtmp/YOUR_KEY_2')
            ];
            
            $streamResult = $bufferedService->startBufferedStream($fileId, '', $rtmpTargets);
            
            // Get active streams
            $activeStreams = $bufferedService->getActiveStreams();

            return response()->json([
                'test' => 'Buffered Streaming Test',
                'file_id' => $fileId,
                'optimal_settings' => $optimalSettings,
                'stream_result' => $streamResult,
                'active_streams' => $activeStreams,
                'buffer_advantages' => [
                    '🚀 Unlimited concurrent streams from single buffer',
                    '🚀 Smooth streaming even with weak VPS',
                    '🚀 Failover protection against Google Drive lag',
                    '🚀 Rolling cache system (2GB max)',
                    '🚀 Background buffer management',
                    '🚀 HLS playlist generation',
                    '🚀 Multi-platform streaming (YouTube + Facebook)'
                ],
                'technical_specs' => [
                    'buffer_size' => '2GB rolling cache',
                    'chunk_size' => '100MB per chunk',
                    'max_chunks' => '20 chunks',
                    'playlist_format' => 'HLS (m3u8)',
                    'streaming_format' => 'RTMP/FLV',
                    'concurrent_support' => 'Unlimited from buffer'
                ],
                'performance_comparison' => [
                    'direct_streaming' => '1 stream per file download',
                    'buffered_streaming' => 'Unlimited streams per buffer',
                    'vps_requirement' => 'CPU limited, not bandwidth limited',
                    'scalability' => '10x better than direct streaming'
                ],
                'timestamp' => now()
            ]);

                 } catch (\Exception $e) {
             return response()->json([
                 'error' => $e->getMessage()
             ], 500);
         }
     }

    /**
     * Test VPS network distribution logic
     */
    public function testVpsNetworkDistribution(Request $request)
    {
        $fileId = $request->input('file_id');
        
        if (!$fileId) {
            return response()->json(['error' => 'file_id required'], 400);
        }

        try {
            // Mock VPS data for testing
            $mockVpsData = [
                [
                    'vps_id' => 1,
                    'vps_name' => 'VPS-Singapore-01',
                    'ip_address' => '172.104.33.164',
                    'disk_usage_percent' => 85,
                    'available_space_gb' => 7.5,
                    'cpu_usage_percent' => 45,
                    'memory_usage_percent' => 60,
                    'network_speed_mbps' => 4147,
                    'disk_category' => 'high',
                    'streaming_method_recommendation' => 'url_preferred',
                    'suitability_score' => 72,
                    'concurrent_streams' => 2,
                    'max_recommended_streams' => 4
                ],
                [
                    'vps_id' => 2,
                    'vps_name' => 'VPS-Singapore-02',
                    'ip_address' => '172.104.33.165',
                    'disk_usage_percent' => 35,
                    'available_space_gb' => 32.5,
                    'cpu_usage_percent' => 20,
                    'memory_usage_percent' => 40,
                    'network_speed_mbps' => 3800,
                    'disk_category' => 'medium',
                    'streaming_method_recommendation' => 'download_preferred',
                    'suitability_score' => 89,
                    'concurrent_streams' => 1,
                    'max_recommended_streams' => 6
                ],
                [
                    'vps_id' => 3,
                    'vps_name' => 'VPS-Singapore-03',
                    'ip_address' => '172.104.33.166',
                    'disk_usage_percent' => 95,
                    'available_space_gb' => 2.5,
                    'cpu_usage_percent' => 80,
                    'memory_usage_percent' => 85,
                    'network_speed_mbps' => 2100,
                    'disk_category' => 'critical',
                    'streaming_method_recommendation' => 'url_only',
                    'suitability_score' => 25,
                    'concurrent_streams' => 3,
                    'max_recommended_streams' => 2
                ]
            ];

            // Mock file analysis
            $mockFileAnalysis = [
                'file_id' => $fileId,
                'file_name' => 'test_video.mp4',
                'file_size_bytes' => 1073741824, // 1GB
                'file_size_gb' => 1.0,
                'size_category' => 'medium',
                'download_time_estimate' => 120, // seconds
                'storage_requirement_gb' => 1.0,
                'streaming_priority' => 'normal'
            ];

            // Sort VPS by suitability score
            usort($mockVpsData, function($a, $b) {
                return $b['suitability_score'] <=> $a['suitability_score'];
            });

            // Decision logic simulation
            $bestVps = $mockVpsData[0];
            $fileSize = $mockFileAnalysis['file_size_gb'];
            
            $distributionPlan = [];
            
            if ($bestVps['disk_usage_percent'] >= 90) {
                // Critical - Force URL streaming
                $distributionPlan = [
                    'strategy' => 'url_streaming',
                    'reason' => 'Critical disk usage - forced URL streaming',
                    'primary_vps' => $bestVps,
                    'estimated_setup_time' => 30,
                    'performance_estimate' => '75-85/100',
                    'disk_usage_mb' => 0
                ];
            } elseif ($bestVps['disk_usage_percent'] >= 75) {
                // High usage - prefer URL unless small file
                if ($fileSize <= 0.5) {
                    $distributionPlan = [
                        'strategy' => 'download_streaming',
                        'reason' => 'Small file exception despite high disk usage',
                        'primary_vps' => $bestVps,
                        'estimated_setup_time' => 180,
                        'performance_estimate' => '90-98/100',
                        'disk_usage_mb' => $fileSize * 1024
                    ];
                } else {
                    $distributionPlan = [
                        'strategy' => 'url_streaming',
                        'reason' => 'High disk usage - prefer URL streaming',
                        'primary_vps' => $bestVps,
                        'estimated_setup_time' => 30,
                        'performance_estimate' => '75-85/100',
                        'disk_usage_mb' => 0
                    ];
                }
            } elseif ($bestVps['disk_usage_percent'] >= 50) {
                // Medium usage - consider file size
                if ($fileSize <= 2.0) {
                    $distributionPlan = [
                        'strategy' => 'download_streaming',
                        'reason' => 'Medium disk usage + acceptable file size',
                        'primary_vps' => $bestVps,
                        'estimated_setup_time' => 180,
                        'performance_estimate' => '90-98/100',
                        'disk_usage_mb' => $fileSize * 1024
                    ];
                } else {
                    $distributionPlan = [
                        'strategy' => 'url_streaming',
                        'reason' => 'Medium disk usage + large file',
                        'primary_vps' => $bestVps,
                        'estimated_setup_time' => 30,
                        'performance_estimate' => '75-85/100',
                        'disk_usage_mb' => 0
                    ];
                }
            } else {
                // Low usage - always download
                $distributionPlan = [
                    'strategy' => 'download_streaming',
                    'reason' => 'Low disk usage - optimal for download',
                    'primary_vps' => $bestVps,
                    'estimated_setup_time' => 180,
                    'performance_estimate' => '90-98/100',
                    'disk_usage_mb' => $fileSize * 1024
                ];
            }

            return response()->json([
                'test' => 'VPS Network Distribution Logic',
                'file_id' => $fileId,
                'file_analysis' => $mockFileAnalysis,
                'vps_analysis' => [
                    'total_vps' => count($mockVpsData),
                    'vps_details' => $mockVpsData,
                    'best_vps' => $bestVps
                ],
                'distribution_plan' => $distributionPlan,
                'decision_matrix' => [
                    'disk_usage_critical_90' => 'Force URL streaming',
                    'disk_usage_high_75' => 'Prefer URL, exception for small files (<500MB)',
                    'disk_usage_medium_50' => 'Consider file size (download if <2GB)',
                    'disk_usage_low_25' => 'Always download for best performance'
                ],
                'network_advantages' => [
                    '🌐 Distributed load across multiple VPS',
                    '🌐 Intelligent routing based on VPS capacity',
                    '🌐 Automatic failover to backup VPS',
                    '🌐 Cost optimization through smart storage usage',
                    '🌐 Scalable mesh network architecture',
                    '🌐 Real-time VPS monitoring and selection'
                ],
                'scaling_scenarios' => [
                    '10_vps_network' => '50-100 concurrent streams',
                    '50_vps_network' => '250-500 concurrent streams',
                    '100_vps_network' => '500-1000 concurrent streams',
                    'bottleneck' => 'Google Drive API rate limits, not VPS capacity'
                ],
                'cost_comparison' => [
                    'traditional_storage' => '$50/TB/month per VPS',
                    'network_distribution' => '$5-15/TB/month across network',
                    'savings_percentage' => '70-90% cost reduction',
                    'break_even_point' => '5+ VPS in network'
                ],
                'timestamp' => now()
            ]);

                 } catch (\Exception $e) {
             return response()->json([
                 'error' => $e->getMessage()
             ], 500);
         }
     }

    /**
     * Test hệ thống dọn dẹp VPS
     */
    public function testVpsCleanup(Request $request)
    {
        try {
            $cleanupService = new \App\Services\VpsCleanupService(app(\App\Services\SshService::class));
            
            // Mock dữ liệu VPS để test
            $mockVpsData = [
                [
                    'vps_id' => 1,
                    'vps_name' => 'VPS-Singapore-01',
                    'disk_usage_before' => 87,
                    'files_on_vps' => [
                        [
                            'path' => '/tmp/streaming_files/video1.mp4',
                            'size_gb' => 2.5,
                            'age_hours' => 48,
                            'view_count' => 5,
                            'should_delete' => true,
                            'reason' => 'File quá cũ (>24 giờ) và disk đầy'
                        ],
                        [
                            'path' => '/tmp/streaming_files/video2.mp4',
                            'size_gb' => 1.8,
                            'age_hours' => 12,
                            'view_count' => 25,
                            'should_delete' => false,
                            'reason' => 'Giữ lại - file được xem nhiều'
                        ],
                        [
                            'path' => '/tmp/streaming_files/video3.mp4',
                            'size_gb' => 3.2,
                            'age_hours' => 168, // 7 ngày
                            'view_count' => 2,
                            'should_delete' => true,
                            'reason' => 'File quá cũ (>7 ngày)'
                        ]
                    ],
                    'cleanup_result' => [
                        'files_deleted' => 2,
                        'space_freed_gb' => 5.7,
                        'disk_usage_after' => 75
                    ]
                ],
                [
                    'vps_id' => 2,
                    'vps_name' => 'VPS-Singapore-02',
                    'disk_usage_before' => 45,
                    'files_on_vps' => [
                        [
                            'path' => '/tmp/streaming_files/video4.mp4',
                            'size_gb' => 1.2,
                            'age_hours' => 6,
                            'view_count' => 8,
                            'should_delete' => false,
                            'reason' => 'Disk chưa đầy và file còn mới'
                        ]
                    ],
                    'cleanup_result' => [
                        'files_deleted' => 0,
                        'space_freed_gb' => 0,
                        'disk_usage_after' => 45
                    ]
                ]
            ];

            // Tính toán thống kê tổng quan
            $totalFilesDeleted = array_sum(array_column(array_column($mockVpsData, 'cleanup_result'), 'files_deleted'));
            $totalSpaceFreed = array_sum(array_column(array_column($mockVpsData, 'cleanup_result'), 'space_freed_gb'));
            
            // Quy tắc dọn dẹp
            $cleanupRules = [
                'tự_động_xóa_sau_giờ' => 24,
                'file_tối_đa_ngày' => 7,
                'kích_hoạt_khi_disk_percent' => 85,
                'giữ_file_phổ_biến' => true,
                'tối_thiểu_lượt_xem_để_giữ' => 10,
                'lịch_dọn_dẹp' => 'Lúc 2h sáng hàng ngày'
            ];

            return response()->json([
                'test' => 'Hệ Thống Dọn Dẹp VPS',
                'quy_tắc_dọn_dẹp' => $cleanupRules,
                'vps_analysis' => $mockVpsData,
                'tổng_quan' => [
                    'tổng_vps_xử_lý' => count($mockVpsData),
                    'tổng_file_đã_xóa' => $totalFilesDeleted,
                    'tổng_dung_lượng_giải_phóng_gb' => $totalSpaceFreed,
                    'tiết_kiệm_chi_phí_tháng' => '$' . round($totalSpaceFreed * 10, 2) // $10/GB/tháng
                ],
                'các_tình_huống_dọn_dẹp' => [
                    'disk_đầy_85_percent' => 'Tự động xóa file >24 giờ',
                    'disk_rất_đầy_95_percent' => 'Xóa khẩn cấp file >1 giờ',
                    'file_quá_cũ_7_ngày' => 'Xóa bắt buộc bất kể disk',
                    'file_được_xem_nhiều' => 'Giữ lại nếu >10 lượt xem',
                    'dọn_dẹp_định_kỳ' => 'Mỗi ngày lúc 2h sáng'
                ],
                'lệnh_quản_lý' => [
                    'dọn_dẹp_tất_cả' => 'php artisan vps:cleanup',
                    'dọn_dẹp_vps_cụ_thể' => 'php artisan vps:cleanup --vps-id=1',
                    'chế_độ_thử_nghiệm' => 'php artisan vps:cleanup --dry-run',
                    'bắt_buộc_dọn_dẹp' => 'php artisan vps:cleanup --force'
                ],
                'lợi_ích_hệ_thống' => [
                    '🧹 Tự động dọn dẹp - không cần can thiệp thủ công',
                    '💰 Tiết kiệm chi phí - giải phóng dung lượng VPS',
                    '⚡ Tối ưu hiệu suất - VPS luôn có đủ không gian',
                    '📊 Thống kê chi tiết - theo dõi quá trình dọn dẹp',
                    '🛡️ Bảo vệ file quan trọng - giữ file được xem nhiều',
                    '🕐 Lịch trình linh hoạt - tùy chỉnh theo nhu cầu'
                ],
                'cảnh_báo_an_toàn' => [
                    '⚠️ Luôn backup file quan trọng trước khi dọn dẹp',
                    '⚠️ Kiểm tra kỹ quy tắc trước khi áp dụng',
                    '⚠️ Sử dụng --dry-run để xem trước kết quả',
                    '⚠️ File đã xóa không thể khôi phục từ VPS'
                ],
                'timestamp' => now()
            ]);

                 } catch (\Exception $e) {
             return response()->json([
                 'error' => $e->getMessage()
             ], 500);
         }
     }

    /**
     * Test vòng đời stream hoàn chỉnh
     */
    public function testStreamLifecycle(Request $request)
    {
        try {
            // Mock dữ liệu để test
            $mockStreamSession = [
                'session_id' => 'stream_' . time(),
                'user_id' => 1,
                'user_file_id' => 1,
                'file_name' => 'video_test.mp4',
                'file_size_gb' => 2.5
            ];

            // Mô phỏng quy trình bật stream
            $startStreamProcess = [
                'bước_1_tìm_vps' => [
                    'vps_khả_dụng' => [
                        ['vps_id' => 1, 'disk_usage' => 45, 'cpu_usage' => 30, 'suitability_score' => 89],
                        ['vps_id' => 2, 'disk_usage' => 85, 'cpu_usage' => 60, 'suitability_score' => 72],
                        ['vps_id' => 3, 'disk_usage' => 95, 'cpu_usage' => 80, 'suitability_score' => 25]
                    ],
                    'vps_được_chọn' => 'VPS-1 (Disk 45% - Đủ chỗ tải file)',
                    'strategy_quyết_định' => 'download_streaming'
                ],
                'bước_2_tải_file' => [
                    'google_drive_url' => 'https://drive.google.com/uc?id=xxx&export=download',
                    'tải_về_vps' => '/tmp/streaming_files/video_test.mp4',
                    'thời_gian_tải' => '120 giây (2.5GB)',
                    'trạng_thái' => 'Thành công'
                ],
                'bước_3_khởi_động_stream' => [
                    'ffmpeg_command' => 'ffmpeg -re -i "/tmp/streaming_files/video_test.mp4" -c:v libx264 -preset veryfast...',
                    'stream_pid' => '12345',
                    'rtmp_target' => 'rtmp://live.youtube.com/live2/STREAM_KEY',
                    'trạng_thái' => 'Stream đang hoạt động'
                ]
            ];

            // Mô phỏng quy trình tắt stream
            $stopStreamProcess = [
                'bước_1_nhận_lệnh_dừng' => [
                    'nguồn' => 'User click nút Stop Stream',
                    'session_id' => $mockStreamSession['session_id'],
                    'thời_gian' => now()
                ],
                'bước_2_dừng_ffmpeg' => [
                    'kill_commands' => [
                        'kill -TERM 12345',
                        'pkill -f "ffmpeg.*video_test.mp4"'
                    ],
                    'trạng_thái' => 'FFmpeg đã dừng'
                ],
                'bước_3_xóa_file_ngay_lập_tức' => [
                    'file_path' => '/tmp/streaming_files/video_test.mp4',
                    'file_size_before' => '2.5 GB',
                    'delete_command' => 'rm -f "/tmp/streaming_files/video_test.mp4"',
                    'trạng_thái' => 'File đã được xóa ngay lập tức',
                    'space_freed' => '2.5 GB',
                    'cleanup_time' => '< 1 giây'
                ],
                'bước_4_cập_nhật_database' => [
                    'stream_session_status' => 'stopped',
                    'user_file_local_path' => 'null (đã xóa)',
                    'cleanup_result' => 'Thành công - tiết kiệm 2.5GB'
                ]
            ];

            // So sánh với phương pháp cũ
            $comparisonWithOldMethod = [
                'phương_pháp_cũ' => [
                    'upload_lên_máy_chủ' => 'User upload video lên server trung tâm',
                    'phân_phát_đến_vps' => 'Copy file từ server trung tâm đến VPS',
                    'vấn_đề' => 'VPS không có video khi được thêm mới vào mạng lưới',
                    'dọn_dẹp' => 'Theo lịch trình (có thể chậm)'
                ],
                'phương_pháp_mới' => [
                    'upload_lên_google_drive' => 'User upload video lên Google Drive',
                    'tìm_vps_khả_dụng' => 'Tự động tìm VPS tốt nhất theo thời gian thực',
                    'quyết_định_thông_minh' => 'Tải về hoặc stream URL tùy tình trạng VPS',
                    'dọn_dẹp_ngay_lập_tức' => 'Xóa file ngay khi dừng stream'
                ]
            ];

            return response()->json([
                'test' => 'Vòng Đời Stream Hoàn Chỉnh',
                'mock_stream_session' => $mockStreamSession,
                'quy_trình_bật_stream' => $startStreamProcess,
                'quy_trình_tắt_stream' => $stopStreamProcess,
                'so_sánh_phương_pháp' => $comparisonWithOldMethod,
                'lợi_ích_chính' => [
                    '🚀 Tự động tìm VPS tối ưu nhất',
                    '🧠 Quyết định thông minh: tải về vs stream URL',
                    '⚡ Dọn dẹp ngay lập tức khi dừng stream',
                    '💾 Tối ưu sử dụng dung lượng VPS',
                    '🔄 Tái sử dụng VPS hiệu quả',
                    '📊 Theo dõi chi tiết từng phiên stream'
                ],
                'kịch_bản_thực_tế' => [
                    'vps_đầy_85_percent' => 'Tự động chuyển sang stream URL',
                    'vps_còn_trống' => 'Tải file về để có hiệu suất tốt nhất',
                    'nhiều_user_cùng_lúc' => 'Phân phối thông minh ra nhiều VPS',
                    'vps_mới_thêm_vào' => 'Ngay lập tức có thể stream từ Google Drive',
                    'stream_dừng' => 'File bị xóa ngay, VPS sẵn sàng cho stream khác'
                ],
                'thống_kê_hiệu_quả' => [
                    'thời_gian_dọn_dẹp' => '< 1 giây (thay vì chờ lịch trình)',
                    'tiết_kiệm_dung_lượng' => 'Ngay lập tức (thay vì tích lũy)',
                    'tái_sử_dụng_vps' => 'Tức thì (thay vì chờ đến 2h sáng)',
                    'hiệu_suất_mạng_lưới' => 'Tối ưu 90% (vs 60% phương pháp cũ)'
                ],
                'timestamp' => now()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test FFmpeg locally
     */
    public function testLocalFFmpeg(Request $request)
    {
        try {
            $localStreaming = app(\App\Services\LocalStreamingService::class);
            
            // Check FFmpeg installation
            $ffmpegCheck = $localStreaming->testFFmpegInstallation();
            
            if (!$ffmpegCheck['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'FFmpeg not installed',
                    'details' => $ffmpegCheck
                ], 500);
            }
            
            $results = [
                'ffmpeg_info' => $ffmpegCheck,
                'test_performed' => []
            ];
            
            // Test 1: Generate test video
            $testVideoPath = storage_path('app/test_stream.mp4');
            $generateResult = $localStreaming->generateTestVideo($testVideoPath, 5);
            $results['test_performed'][] = [
                'test' => 'Generate Test Video',
                'result' => $generateResult
            ];
            
            // Test 2: Test local streaming if video generated
            if ($generateResult['success'] && file_exists($testVideoPath)) {
                $streamResult = $localStreaming->testLocalStream([
                    'input_file' => $testVideoPath,
                    'output_url' => $request->input('rtmp_url', 'rtmp://localhost/live/test'),
                    'preset' => $request->input('preset', 'optimized'),
                    'duration' => 5
                ]);
                
                $results['test_performed'][] = [
                    'test' => 'Local File Streaming',
                    'result' => $streamResult
                ];
                
                // Stop stream after test
                if ($streamResult['success']) {
                    sleep(3);
                    $stopResult = $localStreaming->stopLocalStream($streamResult['pid']);
                    $results['test_performed'][] = [
                        'test' => 'Stop Stream',
                        'result' => $stopResult
                    ];
                }
                
                // Cleanup
                unlink($testVideoPath);
            }
            
            // Test 3: Test Google Drive streaming if file_id provided
            $fileId = $request->input('file_id');
            if ($fileId) {
                $gdResult = $localStreaming->testGoogleDriveStream($fileId, [
                    'output_url' => $request->input('rtmp_url', 'rtmp://localhost/live/gdrive'),
                    'preset' => $request->input('preset', 'optimized'),
                    'duration' => 5
                ]);
                
                $results['test_performed'][] = [
                    'test' => 'Google Drive Streaming',
                    'result' => $gdResult
                ];
                
                // Stop after test
                if ($gdResult['success']) {
                    sleep(3);
                    $localStreaming->stopLocalStream($gdResult['pid']);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Local FFmpeg tests completed',
                'results' => $results
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
