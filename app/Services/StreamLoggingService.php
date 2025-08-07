<?php

namespace App\Services;

use App\Models\StreamConfiguration;
use App\Models\StreamLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Exception;

/**
 * Enhanced logging service for streams, playlist changes, and debugging
 */
class StreamLoggingService
{
    const LOG_LEVELS = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4
    ];

    const LOG_CATEGORIES = [
        'STREAM_LIFECYCLE',
        'PLAYLIST_MANAGEMENT',
        'QUALITY_MONITORING',
        'ERROR_RECOVERY',
        'AGENT_COMMUNICATION',
        'PERFORMANCE',
        'USER_ACTION'
    ];

    /**
     * Log stream event with enhanced context
     */
    public function logStreamEvent(
        int $streamId, 
        string $event, 
        string $level = 'INFO', 
        string $category = 'STREAM_LIFECYCLE',
        array $context = []
    ): void {
        try {
            $stream = StreamConfiguration::find($streamId);
            
            $logData = [
                'stream_id' => $streamId,
                'event' => $event,
                'level' => $level,
                'category' => $category,
                'context' => $context,
                'timestamp' => now()->toISOString(),
                'user_id' => $stream?->user_id,
                'vps_id' => $stream?->vps_server_id,
                'stream_status' => $stream?->status,
                'session_id' => session()->getId()
            ];

            // Store in database
            $this->storeLogInDatabase($logData);

            // Store in Redis for real-time monitoring
            $this->storeLogInRedis($logData);

            // Log to Laravel log
            $this->logToLaravel($level, $event, $logData);

        } catch (Exception $e) {
            Log::error("Failed to log stream event: {$e->getMessage()}", [
                'stream_id' => $streamId,
                'event' => $event
            ]);
        }
    }

    /**
     * Log playlist change
     */
    public function logPlaylistChange(
        int $streamId,
        string $action,
        array $details = []
    ): void {
        $this->logStreamEvent(
            $streamId,
            "Playlist {$action}",
            'INFO',
            'PLAYLIST_MANAGEMENT',
            array_merge($details, [
                'action' => $action,
                'playlist_action_timestamp' => now()->toISOString()
            ])
        );
    }

    /**
     * Log quality metrics
     */
    public function logQualityMetrics(
        int $streamId,
        array $metrics
    ): void {
        $level = $this->determineQualityLevel($metrics);
        
        $this->logStreamEvent(
            $streamId,
            'Quality metrics updated',
            $level,
            'QUALITY_MONITORING',
            [
                'metrics' => $metrics,
                'quality_score' => $this->calculateQualityScore($metrics)
            ]
        );
    }

    /**
     * Log error with recovery attempt
     */
    public function logErrorWithRecovery(
        int $streamId,
        string $error,
        string $recoveryAction = null,
        array $context = []
    ): void {
        $this->logStreamEvent(
            $streamId,
            "Error: {$error}",
            'ERROR',
            'ERROR_RECOVERY',
            array_merge($context, [
                'error_message' => $error,
                'recovery_action' => $recoveryAction,
                'error_timestamp' => now()->toISOString()
            ])
        );
    }

    /**
     * Log agent communication
     */
    public function logAgentCommunication(
        int $vpsId,
        string $command,
        array $payload = [],
        bool $success = true
    ): void {
        $this->logStreamEvent(
            0, // No specific stream
            "Agent command: {$command}",
            $success ? 'INFO' : 'WARNING',
            'AGENT_COMMUNICATION',
            [
                'vps_id' => $vpsId,
                'command' => $command,
                'payload' => $payload,
                'success' => $success,
                'command_timestamp' => now()->toISOString()
            ]
        );
    }

    /**
     * Log performance metrics
     */
    public function logPerformanceMetrics(
        int $streamId,
        array $metrics
    ): void {
        $this->logStreamEvent(
            $streamId,
            'Performance metrics',
            'DEBUG',
            'PERFORMANCE',
            [
                'metrics' => $metrics,
                'performance_timestamp' => now()->toISOString()
            ]
        );
    }

    /**
     * Log user action
     */
    public function logUserAction(
        int $userId,
        string $action,
        int $streamId = null,
        array $details = []
    ): void {
        $this->logStreamEvent(
            $streamId ?? 0,
            "User action: {$action}",
            'INFO',
            'USER_ACTION',
            array_merge($details, [
                'user_id' => $userId,
                'action' => $action,
                'user_action_timestamp' => now()->toISOString()
            ])
        );
    }

    /**
     * Get logs for stream with filtering
     */
    public function getStreamLogs(
        int $streamId,
        array $filters = []
    ): array {
        try {
            $query = StreamLog::where('stream_id', $streamId);

            // Apply filters
            if (isset($filters['level'])) {
                $query->where('level', $filters['level']);
            }

            if (isset($filters['category'])) {
                $query->where('category', $filters['category']);
            }

            if (isset($filters['from_date'])) {
                $query->where('created_at', '>=', $filters['from_date']);
            }

            if (isset($filters['to_date'])) {
                $query->where('created_at', '<=', $filters['to_date']);
            }

            $logs = $query->orderBy('created_at', 'desc')
                         ->limit($filters['limit'] ?? 100)
                         ->get();

            return [
                'success' => true,
                'logs' => $logs->toArray(),
                'total' => $logs->count()
            ];

        } catch (Exception $e) {
            Log::error("Failed to get stream logs: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get real-time logs from Redis
     */
    public function getRealTimeLogs(
        int $streamId = null,
        int $limit = 50
    ): array {
        try {
            $key = $streamId ? "stream_logs:{$streamId}" : "stream_logs:all";
            $logs = Redis::lrange($key, 0, $limit - 1);

            return [
                'success' => true,
                'logs' => array_map('json_decode', $logs),
                'total' => count($logs)
            ];

        } catch (Exception $e) {
            Log::error("Failed to get real-time logs: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Store log in database
     */
    private function storeLogInDatabase(array $logData): void
    {
        try {
            StreamLog::create([
                'stream_id' => $logData['stream_id'],
                'event' => $logData['event'],
                'level' => $logData['level'],
                'category' => $logData['category'],
                'context' => $logData['context'],
                'user_id' => $logData['user_id'],
                'vps_id' => $logData['vps_id'],
                'session_id' => $logData['session_id']
            ]);
        } catch (Exception $e) {
            Log::error("Failed to store log in database: {$e->getMessage()}");
        }
    }

    /**
     * Store log in Redis for real-time access
     */
    private function storeLogInRedis(array $logData): void
    {
        try {
            $streamKey = "stream_logs:{$logData['stream_id']}";
            $allKey = "stream_logs:all";

            // Store in stream-specific list
            Redis::lpush($streamKey, json_encode($logData));
            Redis::ltrim($streamKey, 0, 999); // Keep last 1000 logs
            Redis::expire($streamKey, 86400); // 24 hours TTL

            // Store in global list
            Redis::lpush($allKey, json_encode($logData));
            Redis::ltrim($allKey, 0, 4999); // Keep last 5000 logs
            Redis::expire($allKey, 86400);

        } catch (Exception $e) {
            Log::error("Failed to store log in Redis: {$e->getMessage()}");
        }
    }

    /**
     * Log to Laravel log system
     */
    private function logToLaravel(string $level, string $event, array $context): void
    {
        $message = "[Stream {$context['stream_id']}] {$event}";
        
        switch (strtoupper($level)) {
            case 'DEBUG':
                Log::debug($message, $context);
                break;
            case 'INFO':
                Log::info($message, $context);
                break;
            case 'WARNING':
                Log::warning($message, $context);
                break;
            case 'ERROR':
                Log::error($message, $context);
                break;
            case 'CRITICAL':
                Log::critical($message, $context);
                break;
            default:
                Log::info($message, $context);
        }
    }

    /**
     * Determine quality level based on metrics
     */
    private function determineQualityLevel(array $metrics): string
    {
        $score = $this->calculateQualityScore($metrics);
        
        if ($score >= 90) return 'DEBUG';
        if ($score >= 70) return 'INFO';
        if ($score >= 50) return 'WARNING';
        return 'ERROR';
    }

    /**
     * Calculate quality score from metrics
     */
    private function calculateQualityScore(array $metrics): int
    {
        $score = 100;
        
        // Deduct points for issues
        if (isset($metrics['dropped_frames']) && $metrics['dropped_frames'] > 0) {
            $score -= min(30, $metrics['dropped_frames']);
        }
        
        if (isset($metrics['bitrate_stability']) && $metrics['bitrate_stability'] < 0.9) {
            $score -= 20;
        }
        
        if (isset($metrics['connection_errors']) && $metrics['connection_errors'] > 0) {
            $score -= min(40, $metrics['connection_errors'] * 10);
        }
        
        return max(0, $score);
    }

    /**
     * Clean up old logs
     */
    public function cleanupOldLogs(int $daysToKeep = 30): int
    {
        try {
            $cutoffDate = now()->subDays($daysToKeep);
            $deletedCount = StreamLog::where('created_at', '<', $cutoffDate)->delete();
            
            Log::info("Cleaned up {$deletedCount} old stream logs");
            return $deletedCount;
            
        } catch (Exception $e) {
            Log::error("Failed to cleanup old logs: {$e->getMessage()}");
            return 0;
        }
    }
}
