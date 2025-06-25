<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Models\UserFile;
use App\Models\VpsServer;

class OptimizedStreamingService
{
    private $googleDriveService;
    private $cachePrefix = 'optimized_streaming_';
    private $cacheTtl = 1800; // 30 minutes
    
    // Improved performance thresholds
    private $performanceThresholds = [
        'min_speed_mbps' => 2.0,
        'max_response_time_ms' => 800, // Reduced from 1000
        'excellent_speed_mbps' => 10.0,
        'good_speed_mbps' => 5.0,
        'max_excellent_response_ms' => 300,
        'max_good_response_ms' => 600,
    ];

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    /**
     * Get optimized streaming URL with multiple fallback strategies
     */
    public function getOptimizedStreamingUrl($fileId, $forceRefresh = false)
    {
        $cacheKey = $this->cachePrefix . 'url_' . $fileId;
        
        if (!$forceRefresh && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Enhanced fallback strategies with better performance
        $strategies = [
            'api_method' => function($fileId) {
                try {
                    $result = $this->googleDriveService->getDirectDownloadLink($fileId);
                    return $result['success'] ? $result['download_link'] : null;
                } catch (\Exception $e) {
                    return null;
                }
            },
            'direct_download' => function($fileId) {
                return "https://drive.google.com/uc?id={$fileId}&export=download";
            },
            'direct_download_v2' => function($fileId) {
                return "https://drive.google.com/uc?id={$fileId}&export=download&confirm=t";
            },
            'export_download' => function($fileId) {
                return "https://docs.google.com/uc?export=download&id={$fileId}";
            },
            'webview_extract' => function($fileId) {
                return "https://drive.google.com/file/d/{$fileId}/view";
            }
        ];

        $bestUrl = null;
        $bestPerformance = null;
        $bestMethod = null;

        foreach ($strategies as $method => $strategy) {
            try {
                $url = $strategy($fileId);
                if (!$url) continue;

                // Quick performance test with timeout optimization
                $performance = $this->testUrlPerformance($url, true); // Quick test
                
                if ($performance['accessible']) {
                    // Score calculation with improved weighting
                    $score = $this->calculatePerformanceScore($performance);
                    
                    if (!$bestPerformance || $score > $bestPerformance['score']) {
                        $bestUrl = $url;
                        $bestPerformance = array_merge($performance, ['score' => $score]);
                        $bestMethod = $method;
                    }
                    
                    // If we get excellent performance, use it immediately
                    if ($score >= 80) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Strategy {$method} failed: " . $e->getMessage());
                continue;
            }
        }

        if ($bestUrl) {
            $result = [
                'success' => true,
                'url' => $bestUrl,
                'method' => $bestMethod,
                'performance' => $bestPerformance,
                'cached_until' => now()->addSeconds($this->cacheTtl)->toISOString()
            ];
            
            Cache::put($cacheKey, $result, $this->cacheTtl);
            return $result;
        }

        return [
            'success' => false,
            'error' => 'No accessible URL found',
            'tested_methods' => array_keys($strategies)
        ];
    }

    /**
     * Enhanced performance testing with quick mode
     */
    public function testUrlPerformance($url, $quickMode = false)
    {
        $startTime = microtime(true);
        
        try {
            // Optimized HTTP client settings
            $client = Http::timeout($quickMode ? 5 : 10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'video/*,*/*;q=0.9',
                    'Accept-Encoding' => 'identity', // Disable compression for speed
                    'Connection' => 'keep-alive',
                    'Range' => 'bytes=0-' . ($quickMode ? '102400' : '1048576') // 100KB quick, 1MB full
                ]);

            $response = $client->get($url);
            $responseTime = (microtime(true) - $startTime) * 1000;
            
            if ($response->successful()) {
                $downloadedBytes = strlen($response->body());
                $downloadTime = (microtime(true) - $startTime) * 1000;
                $speedMbps = $downloadedBytes > 0 ? 
                    ($downloadedBytes * 8 / 1000000) / ($downloadTime / 1000) : 0;

                return [
                    'accessible' => true,
                    'response_time_ms' => round($responseTime, 2),
                    'downloaded_bytes' => $downloadedBytes,
                    'speed_mbps' => round($speedMbps, 2),
                    'content_length' => $response->header('Content-Length', ''),
                    'content_type' => $response->header('Content-Type', ''),
                    'accepts_ranges' => $response->header('Accept-Ranges', '') === 'bytes'
                ];
            }
        } catch (\Exception $e) {
            Log::warning("Performance test failed for URL: {$url}, Error: " . $e->getMessage());
        }

        return [
            'accessible' => false,
            'response_time_ms' => (microtime(true) - $startTime) * 1000,
            'error' => 'Connection failed'
        ];
    }

    /**
     * Improved performance score calculation with Google Drive optimizations
     */
    private function calculatePerformanceScore($performance)
    {
        if (!$performance['accessible']) {
            return 0;
        }

        $score = 0;
        $responseTime = $performance['response_time_ms'];
        $speed = $performance['speed_mbps'];

        // Response time scoring (35% weight) - More lenient for Google Drive
        if ($responseTime <= 500) {
            $score += 35;
        } elseif ($responseTime <= 1000) {
            $score += 30;
        } elseif ($responseTime <= 2000) {
            $score += 25;
        } elseif ($responseTime <= 3000) {
            $score += 20;
        } else {
            $score += 10;
        }

        // Speed scoring (35% weight) - Adjusted for streaming requirements
        if ($speed >= 10.0) {
            $score += 35;
        } elseif ($speed >= 5.0) {
            $score += 30;
        } elseif ($speed >= 3.0) {
            $score += 25;
        } elseif ($speed >= 2.0) {
            $score += 20;
        } else {
            $score += 10;
        }

        // Bonus points (30% weight) - More generous scoring
        // Basic accessibility bonus
        $score += 15; // Base score for being accessible
        
        if (isset($performance['accepts_ranges']) && $performance['accepts_ranges']) {
            $score += 5; // Range requests support
        }
        if (isset($performance['content_length']) && !empty($performance['content_length'])) {
            $score += 5; // Content-Length header present
        }
        
        // Content type bonus - more flexible for Google Drive
        if (isset($performance['content_type'])) {
            $contentType = $performance['content_type'];
            if (strpos($contentType, 'video/') === 0) {
                $score += 5; // Perfect video content type
            } elseif (strpos($contentType, 'application/') === 0 || strpos($contentType, 'binary/') === 0) {
                $score += 3; // Binary content (likely video)
            } else {
                $score += 1; // At least has content type
            }
        }

        return min(100, $score);
    }

    /**
     * Enhanced health monitoring with detailed analysis
     */
    public function monitorStreamingHealth($fileId)
    {
        $optimizedResult = $this->getOptimizedStreamingUrl($fileId);
        
        if (!$optimizedResult['success']) {
            return [
                'status' => 'unhealthy',
                'error' => $optimizedResult['error'],
                'timestamp' => now()->toISOString()
            ];
        }

        // Perform detailed health check
        $healthCheck = $this->performHealthCheck($optimizedResult['url']);
        
        $status = $healthCheck['healthy'] ? 'healthy' : 'unhealthy';
        $score = $healthCheck['score'];
        
        // Enhanced recommendations
        $recommendations = $this->generateRecommendations($score, $optimizedResult['method'], $healthCheck);

        return [
            'status' => $status,
            'url' => $optimizedResult['url'],
            'method' => $optimizedResult['method'],
            'performance' => $optimizedResult['performance'],
            'health_check' => $healthCheck,
            'recommendations' => $recommendations,
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Enhanced health check with multiple criteria
     */
    private function performHealthCheck($url)
    {
        // Test accessibility
        $accessibilityTest = $this->testUrlPerformance($url, true);
        
        // Test streaming capability
        $streamingTest = $this->testStreamingCapability($url);
        
        $checks = [
            'accessibility' => [
                'passed' => $accessibilityTest['accessible'],
                'response_time_ms' => $accessibilityTest['response_time_ms'] ?? 0,
                'status_code' => $accessibilityTest['accessible'] ? 200 : 0
            ],
            'speed' => [
                'passed' => ($accessibilityTest['speed_mbps'] ?? 0) >= $this->performanceThresholds['min_speed_mbps'],
                'speed_mbps' => $accessibilityTest['speed_mbps'] ?? 0,
                'download_time_ms' => $accessibilityTest['response_time_ms'] ?? 0
            ],
            'streaming' => [
                'passed' => $streamingTest['streaming_ready'],
                'supports_ranges' => $streamingTest['supports_ranges'],
                'content_type_valid' => $streamingTest['content_type_valid']
            ]
        ];

        $score = $this->calculatePerformanceScore($accessibilityTest);
        $allPassed = collect($checks)->every(fn($check) => $check['passed']);

        return [
            'healthy' => $allPassed && $score >= 50,
            'checks' => $checks,
            'score' => $score
        ];
    }

    /**
     * Test streaming-specific capabilities
     */
    private function testStreamingCapability($url)
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Range' => 'bytes=0-1023'
                ])
                ->get($url);

            return [
                'streaming_ready' => $response->successful(),
                'supports_ranges' => $response->header('Accept-Ranges') === 'bytes',
                'content_type_valid' => strpos($response->header('Content-Type', ''), 'video/') === 0
            ];
        } catch (\Exception $e) {
            return [
                'streaming_ready' => false,
                'supports_ranges' => false,
                'content_type_valid' => false
            ];
        }
    }

    /**
     * Generate intelligent recommendations
     */
    private function generateRecommendations($score, $method, $healthCheck)
    {
        $recommendations = [];

        if ($score >= 90) {
            $recommendations[] = "🟢 Excellent performance - Ready for production streaming";
        } elseif ($score >= 70) {
            $recommendations[] = "🟡 Good performance - Consider minor optimizations";
            if (!$healthCheck['checks']['streaming']['supports_ranges']) {
                $recommendations[] = "Enable range requests support for better streaming";
            }
        } elseif ($score >= 50) {
            $recommendations[] = "🟠 Fair performance - Consider switching to better method";
            $recommendations[] = "Test other URL strategies for improved performance";
        } else {
            $recommendations[] = "🔴 Poor performance - Switch to alternative URL immediately";
            $recommendations[] = "Consider using CDN proxy or direct VPS upload";
        }

        // Method-specific recommendations
        if ($method === 'api_method' && $score < 70) {
            $recommendations[] = "API method performance is suboptimal - try direct_download_v2";
        }

        if ($healthCheck['checks']['accessibility']['response_time_ms'] > 1000) {
            $recommendations[] = "High latency detected - consider geographic optimization";
        }

        return $recommendations;
    }

    /**
     * Generate optimized FFmpeg command with enhanced settings
     */
    public function generateOptimizedFFmpegCommand($streamingUrl, $rtmpUrl, $options = [])
    {
        $defaultOptions = [
            'video_bitrate' => '2500k',
            'audio_bitrate' => '128k',
            'fps' => '30',
            'resolution' => '1280x720',
            'preset' => 'veryfast',
            'threads' => '4'
        ];

        $options = array_merge($defaultOptions, $options);

        // Enhanced FFmpeg command with better error handling
        $command = "ffmpeg -re -threads {$options['threads']} " .
                  "-fflags +genpts+discardcorrupt+igndts " .
                  "-headers \"User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\" " .
                  "-reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5 " .
                  "-reconnect_at_eof 1 " .
                  "-i \"{$streamingUrl}\" " .
                  "-c:v libx264 -preset {$options['preset']} -tune zerolatency " .
                  "-b:v {$options['video_bitrate']} -maxrate {$options['video_bitrate']} " .
                  "-bufsize " . (intval($options['video_bitrate']) * 2) . "k " .
                  "-r {$options['fps']} -s {$options['resolution']} " .
                  "-c:a aac -b:a {$options['audio_bitrate']} -ar 44100 " .
                  "-f flv -flvflags no_duration_filesize " .
                  "-max_muxing_queue_size 1024 " .
                  "\"{$rtmpUrl}\"";

        return $command;
    }

    /**
     * Batch performance comparison with detailed analysis
     */
    public function batchPerformanceComparison($fileIds)
    {
        $results = [];
        $scores = [];

        foreach ($fileIds as $fileId) {
            $healthData = $this->monitorStreamingHealth($fileId);
            $results[$fileId] = $healthData;
            
            if (isset($healthData['health_check']['score'])) {
                $scores[$fileId] = $healthData['health_check']['score'];
            }
        }

        // Find best performer
        $bestPerformer = !empty($scores) ? array_keys($scores, max($scores))[0] : null;
        $averageScore = !empty($scores) ? array_sum($scores) / count($scores) : 0;

        // Generate batch recommendations
        $batchRecommendations = [];
        if ($averageScore >= 80) {
            $batchRecommendations[] = "🟢 Overall excellent performance across all files";
        } elseif ($averageScore >= 60) {
            $batchRecommendations[] = "🟡 Good overall performance with room for improvement";
        } else {
            $batchRecommendations[] = "⚠️ Performance needs significant improvement";
            $batchRecommendations[] = "Consider implementing CDN proxy or alternative hosting";
        }

        return [
            'total_files' => count($fileIds),
            'results' => $results,
            'best_performer' => $bestPerformer,
            'average_score' => round($averageScore, 1),
            'recommendations' => $batchRecommendations,
            'timestamp' => now()->toISOString()
        ];
    }
} 