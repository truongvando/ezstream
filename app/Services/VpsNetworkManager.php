<?php

namespace App\Services;

use App\Models\VpsServer;
use App\Models\UserFile;
use App\Models\StreamConfiguration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class VpsNetworkManager
{
    private $sshService;
    private $optimizedStreamingService;
    
    // Thresholds for decision making
    private $diskUsageThresholds = [
        'critical' => 90,  // >90% = Force URL streaming
        'high' => 75,      // >75% = Prefer URL streaming  
        'medium' => 50,    // >50% = Consider file size
        'low' => 25        // <25% = Always download
    ];
    
    private $fileSizeThresholds = [
        'small' => 500 * 1024 * 1024,    // 500MB
        'medium' => 2 * 1024 * 1024 * 1024,  // 2GB
        'large' => 5 * 1024 * 1024 * 1024   // 5GB
    ];

    public function __construct(SshService $sshService, OptimizedStreamingService $optimizedStreamingService)
    {
        $this->sshService = $sshService;
        $this->optimizedStreamingService = $optimizedStreamingService;
    }

    /**
     * Intelligent stream distribution logic
     */
    public function distributeStream($userFileId, $streamConfig)
    {
        try {
            $userFile = UserFile::findOrFail($userFileId);
            $availableVps = $this->getAvailableVps();
            
            if (empty($availableVps)) {
                return ['success' => false, 'error' => 'No VPS available'];
            }
            
            // Analyze file and VPS conditions
            $fileAnalysis = $this->analyzeFile($userFile);
            $vpsAnalysis = $this->analyzeVpsPool($availableVps);
            
            // Decision engine
            $distributionPlan = $this->createDistributionPlan($fileAnalysis, $vpsAnalysis, $streamConfig);
            
            // Execute distribution
            $executionResult = $this->executeDistribution($distributionPlan);
            
            return [
                'success' => true,
                'file_analysis' => $fileAnalysis,
                'vps_analysis' => $vpsAnalysis,
                'distribution_plan' => $distributionPlan,
                'execution_result' => $executionResult,
                'timestamp' => now()
            ];
            
        } catch (\Exception $e) {
            Log::error("Stream distribution failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analyze file characteristics
     */
    private function analyzeFile($userFile)
    {
        $fileSize = $userFile->file_size ?? 0;
        $fileSizeCategory = $this->categorizeFileSize($fileSize);
        
        return [
            'file_id' => $userFile->google_drive_file_id,
            'file_name' => $userFile->file_name,
            'file_size_bytes' => $fileSize,
            'file_size_gb' => round($fileSize / 1024 / 1024 / 1024, 2),
            'size_category' => $fileSizeCategory,
            'download_time_estimate' => $this->estimateDownloadTime($fileSize),
            'storage_requirement_gb' => round($fileSize / 1024 / 1024 / 1024, 2),
            'streaming_priority' => $this->calculateStreamingPriority($userFile)
        ];
    }

    /**
     * Analyze VPS pool status
     */
    private function analyzeVpsPool($vpsServers)
    {
        $analysis = [
            'total_vps' => count($vpsServers),
            'vps_details' => [],
            'recommendations' => []
        ];
        
        foreach ($vpsServers as $vps) {
            $vpsStatus = $this->getVpsStatus($vps);
            $analysis['vps_details'][] = $vpsStatus;
        }
        
        // Sort by suitability score
        usort($analysis['vps_details'], function($a, $b) {
            return $b['suitability_score'] <=> $a['suitability_score'];
        });
        
        return $analysis;
    }

    /**
     * Get detailed VPS status
     */
    private function getVpsStatus($vps)
    {
        try {
            // Get real-time stats via SSH
            $diskUsagePercent = $this->sshService->getDiskUsage($vps);
            $availableSpaceGB = $this->sshService->getAvailableDiskSpace($vps);
            $cpuUsage = $this->sshService->getCpuUsage($vps);
            $memoryUsage = $this->sshService->getRamUsage($vps);
            
            // TODO: Implement a real network speed test. For now, assume 1Gbps.
            $networkSpeed = 1000; // Mbp

            return [
                'vps_id' => $vps->id,
                'vps_name' => $vps->name,
                'ip_address' => $vps->ip_address,
                'disk_usage_percent' => $diskUsagePercent,
                'available_space_gb' => $availableSpaceGB,
                'cpu_usage_percent' => $cpuUsage,
                'memory_usage_percent' => $memoryUsage,
                'network_speed_mbps' => $networkSpeed,
                'disk_category' => $this->categorizeDiskUsage($diskUsagePercent),
                'streaming_method_recommendation' => $this->recommendStreamingMethod($diskUsagePercent, $availableSpaceGB),
                'suitability_score' => $this->calculateSuitabilityScore($diskUsagePercent, $cpuUsage, $memoryUsage, $networkSpeed),
                'concurrent_streams' => $this->getCurrentStreamCount($vps),
                'max_recommended_streams' => $this->getMaxRecommendedStreams($vps)
            ];
            
        } catch (\Exception $e) {
            Log::warning("Failed to get VPS status for {$vps->id}: " . $e->getMessage());
            return [
                'vps_id' => $vps->id,
                'error' => 'Status unavailable',
                'suitability_score' => 0
            ];
        }
    }

    /**
     * Create intelligent distribution plan
     */
    private function createDistributionPlan($fileAnalysis, $vpsAnalysis, $streamConfig)
    {
        $plan = [
            'strategy' => '',
            'primary_vps' => null,
            'backup_vps' => [],
            'streaming_method' => '',
            'estimated_setup_time' => 0,
            'cost_analysis' => [],
            'risk_assessment' => []
        ];
        
        $fileSize = $fileAnalysis['file_size_gb'];
        $bestVps = $vpsAnalysis['vps_details'][0] ?? null;
        
        if (!$bestVps) {
            $plan['strategy'] = 'no_vps_available';
            return $plan;
        }
        
        // Decision matrix
        if ($bestVps['disk_usage_percent'] >= $this->diskUsageThresholds['critical']) {
            // Critical disk usage - Force URL streaming
            $plan = $this->planUrlStreaming($fileAnalysis, $bestVps, $vpsAnalysis);
            
        } elseif ($bestVps['disk_usage_percent'] >= $this->diskUsageThresholds['high']) {
            // High disk usage - Prefer URL streaming unless file is very small
            if ($fileSize <= 0.5) { // 500MB
                $plan = $this->planDownloadStreaming($fileAnalysis, $bestVps, $vpsAnalysis);
            } else {
                $plan = $this->planUrlStreaming($fileAnalysis, $bestVps, $vpsAnalysis);
            }
            
        } elseif ($bestVps['disk_usage_percent'] >= $this->diskUsageThresholds['medium']) {
            // Medium disk usage - Consider file size
            if ($fileSize <= 2.0) { // 2GB
                $plan = $this->planDownloadStreaming($fileAnalysis, $bestVps, $vpsAnalysis);
            } else {
                $plan = $this->planUrlStreaming($fileAnalysis, $bestVps, $vpsAnalysis);
            }
            
        } else {
            // Low disk usage - Always download for better performance
            $plan = $this->planDownloadStreaming($fileAnalysis, $bestVps, $vpsAnalysis);
        }
        
        // Add backup strategies
        $plan['backup_vps'] = array_slice($vpsAnalysis['vps_details'], 1, 2);
        $plan['fallback_strategy'] = 'url_streaming'; // Always fallback to URL streaming
        
        return $plan;
    }

    /**
     * Plan URL streaming strategy
     */
    private function planUrlStreaming($fileAnalysis, $primaryVps, $vpsAnalysis)
    {
        return [
            'strategy' => 'url_streaming',
            'primary_vps' => $primaryVps,
            'streaming_method' => 'direct_from_google_drive',
            'estimated_setup_time' => 30, // seconds
            'disk_usage_mb' => 0,
            'advantages' => [
                'No disk space required',
                'Instant start',
                'No download time',
                'Preserves VPS storage'
            ],
            'disadvantages' => [
                'Dependent on Google Drive speed',
                'Potential latency issues',
                'Limited concurrent streams per file'
            ],
            'performance_estimate' => '75-85/100',
            'cost_per_hour' => 0.01 // Minimal cost
        ];
    }

    /**
     * Plan download streaming strategy
     */
    private function planDownloadStreaming($fileAnalysis, $primaryVps, $vpsAnalysis)
    {
        $downloadTime = $this->estimateDownloadTime($fileAnalysis['file_size_bytes'], $primaryVps['network_speed_mbps']);
        
        return [
            'strategy' => 'download_streaming',
            'primary_vps' => $primaryVps,
            'streaming_method' => 'local_file_streaming',
            'estimated_setup_time' => $downloadTime + 60, // download + setup
            'disk_usage_mb' => $fileAnalysis['file_size_bytes'] / 1024 / 1024,
            'advantages' => [
                'Best streaming performance',
                'Unlimited concurrent streams',
                'No dependency on external services',
                'Lowest latency'
            ],
            'disadvantages' => [
                'Uses VPS disk space',
                'Download time required',
                'Higher initial setup cost'
            ],
            'performance_estimate' => '90-98/100',
            'cost_per_hour' => 0.05 // Higher due to storage
        ];
    }

    /**
     * Execute the distribution plan
     */
    private function executeDistribution($plan)
    {
        $results = [];
        
        try {
            switch ($plan['strategy']) {
                case 'url_streaming':
                    $results = $this->executeUrlStreaming($plan);
                    break;
                    
                case 'download_streaming':
                    $results = $this->executeDownloadStreaming($plan);
                    break;
                    
                default:
                    $results = ['success' => false, 'error' => 'Unknown strategy'];
            }
            
        } catch (\Exception $e) {
            Log::error("Execution failed: " . $e->getMessage());
            $results = ['success' => false, 'error' => $e->getMessage()];
        }
        
        return $results;
    }

    /**
     * Execute URL streaming
     */
    private function executeUrlStreaming($plan)
    {
        $vps = $plan['primary_vps'];
        
        // Get optimized streaming URL
        $urlResult = $this->optimizedStreamingService->getOptimizedStreamingUrl(
            $plan['file_analysis']['file_id']
        );
        
        if (!$urlResult['success']) {
            return ['success' => false, 'error' => 'Cannot get streaming URL'];
        }
        
        // Generate FFmpeg command for VPS
        $ffmpegCommand = $this->optimizedStreamingService->generateOptimizedFFmpegCommand(
            $urlResult['url'],
            'rtmp://target-platform.com/live/STREAM_KEY'
        );
        
        // Execute on VPS via SSH
        $sshResult = $this->sshService->executeCommand($vps, $ffmpegCommand, true); // background
        
        return [
            'success' => true,
            'execution_method' => 'url_streaming',
            'vps_used' => $vps['vps_id'],
            'streaming_url' => $urlResult['url'],
            'ffmpeg_command' => $ffmpegCommand,
            'ssh_result' => $sshResult,
            'estimated_performance' => '80-90/100'
        ];
    }

    /**
     * Execute download streaming
     */
    private function executeDownloadStreaming($plan)
    {
        $vps = $plan['primary_vps'];
        
        // First, download file to VPS
        $downloadResult = $this->downloadFileToVps($plan, $vps);
        
        if (!$downloadResult['success']) {
            // Fallback to URL streaming
            return $this->executeUrlStreaming($plan);
        }
        
        // Stream from local file
        $localFilePath = $downloadResult['local_path'];
        $ffmpegCommand = sprintf(
            'ffmpeg -re -i "%s" -c:v libx264 -preset veryfast -tune zerolatency ' .
            '-b:v 2500k -maxrate 2500k -bufsize 5000k -r 30 -s 1280x720 ' .
            '-c:a aac -b:a 128k -ar 44100 -f flv "rtmp://target-platform.com/live/STREAM_KEY"',
            $localFilePath
        );
        
        $sshResult = $this->sshService->executeCommand($vps, $ffmpegCommand, true);
        
        return [
            'success' => true,
            'execution_method' => 'download_streaming',
            'vps_used' => $vps['vps_id'],
            'local_file_path' => $localFilePath,
            'download_result' => $downloadResult,
            'ffmpeg_command' => $ffmpegCommand,
            'ssh_result' => $sshResult,
            'estimated_performance' => '95-99/100'
        ];
    }

    /**
     * Download file to VPS
     */
    private function downloadFileToVps($plan, $vps)
    {
        try {
            // Get optimized streaming URL
            $urlResult = $this->optimizedStreamingService->getOptimizedStreamingUrl(
                $plan['file_analysis']['file_id']
            );
            
            if (!$urlResult['success']) {
                return ['success' => false, 'error' => 'Cannot get download URL'];
            }
            
            $downloadUrl = $urlResult['url'];
            $fileName = $plan['file_analysis']['file_name'];
            $localPath = "/tmp/streaming_files/{$fileName}";
            
            // Create download command
            $downloadCommand = sprintf(
                'mkdir -p /tmp/streaming_files && wget -O "%s" --header="User-Agent: Mozilla/5.0" "%s"',
                $localPath,
                $downloadUrl
            );
            
            // Execute download on VPS
            $downloadResult = $this->sshService->executeCommand($vps, $downloadCommand);
            
            if ($downloadResult['success']) {
                return [
                    'success' => true,
                    'local_path' => $localPath,
                    'download_command' => $downloadCommand,
                    'download_result' => $downloadResult
                ];
            } else {
                return ['success' => false, 'error' => 'Download failed'];
            }
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Helper methods
    private function getAvailableVps()
    {
        return VpsServer::where('status', 'active')->get();
    }
    
    private function categorizeFileSize($sizeBytes)
    {
        if ($sizeBytes < $this->fileSizeThresholds['small']) return 'small';
        if ($sizeBytes < $this->fileSizeThresholds['medium']) return 'medium';
        return 'large';
    }
    
    private function categorizeDiskUsage($usagePercent)
    {
        if ($usagePercent >= $this->diskUsageThresholds['critical']) return 'critical';
        if ($usagePercent >= $this->diskUsageThresholds['high']) return 'high';
        if ($usagePercent >= $this->diskUsageThresholds['medium']) return 'medium';
        return 'low';
    }
    
    private function recommendStreamingMethod($diskUsage, $availableSpace)
    {
        if ($diskUsage >= 90) return 'url_only';
        if ($diskUsage >= 75) return 'url_preferred';
        if ($availableSpace >= 5) return 'download_preferred';
        return 'download_only';
    }
    
    private function calculateSuitabilityScore($disk, $cpu, $memory, $network)
    {
        $score = 100;
        $score -= $disk * 0.5;      // Disk usage penalty
        $score -= $cpu * 0.3;       // CPU usage penalty  
        $score -= $memory * 0.2;    // Memory usage penalty
        $score += min($network / 100, 20); // Network speed bonus
        
        return max(0, round($score));
    }
    
    private function estimateDownloadTime($fileSizeBytes, $networkSpeedMbps = 100)
    {
        $fileSizeMb = $fileSizeBytes / 1024 / 1024;
        $timeSeconds = ($fileSizeMb * 8) / $networkSpeedMbps; // Convert to bits and calculate
        return round($timeSeconds);
    }
    
    private function calculateStreamingPriority($userFile)
    {
        // Calculate based on user subscription, file popularity, etc.
        return 'normal'; // placeholder
    }
    
    private function getCurrentStreamCount($vps)
    {
        // Count active streaming processes on VPS
        return 0; // placeholder
    }
    
    private function getMaxRecommendedStreams($vps)
    {
        // Calculate based on VPS specs
        return $vps->cpu_cores * 2; // 2 streams per core
    }
} 