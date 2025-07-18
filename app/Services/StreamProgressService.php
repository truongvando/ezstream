<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class StreamProgressService
{
    private const PROGRESS_KEY_PREFIX = 'stream_progress:';
    private const PROGRESS_TTL = 1800; // 30 minutes TTL

    /**
     * Set stream progress in Redis (temporary storage)
     */
    public static function setProgress(
        int $streamId,
        string $stage,
        int $progressPercentage,
        string $message,
        array $details = null
    ): bool {
        try {
            $key = self::PROGRESS_KEY_PREFIX . $streamId;
            
            $progressData = [
                'stream_id' => $streamId,
                'stage' => $stage,
                'progress_percentage' => max(0, min(100, $progressPercentage)),
                'message' => $message,
                'details' => $details,
                'updated_at' => now()->toISOString(),
                'completed_at' => $progressPercentage >= 100 ? now()->toISOString() : null
            ];

            // Store in Redis with TTL
            Redis::setex($key, self::PROGRESS_TTL, json_encode($progressData));
            
            Log::debug("Stream progress cached", [
                'stream_id' => $streamId,
                'stage' => $stage,
                'progress' => $progressPercentage
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error("Failed to cache stream progress", [
                'stream_id' => $streamId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get stream progress from Redis
     */
    public static function getProgress(int $streamId): ?array
    {
        try {
            $key = self::PROGRESS_KEY_PREFIX . $streamId;
            $data = Redis::get($key);
            
            if ($data) {
                return json_decode($data, true);
            }
            
            return null;

        } catch (\Exception $e) {
            Log::error("Failed to get stream progress from cache", [
                'stream_id' => $streamId,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Clear stream progress from Redis
     */
    public static function clearProgress(int $streamId): bool
    {
        try {
            $key = self::PROGRESS_KEY_PREFIX . $streamId;
            Redis::del($key);
            
            Log::debug("Stream progress cleared from cache", [
                'stream_id' => $streamId
            ]);
            
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to clear stream progress from cache", [
                'stream_id' => $streamId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get default progress based on stream status
     */
    public static function getDefaultProgress(string $status, int $streamId): array
    {
        switch ($status) {
            case 'STARTING':
                return [
                    'stream_id' => $streamId,
                    'stage' => 'preparing',
                    'progress_percentage' => 10,
                    'message' => 'Đang chuẩn bị stream...',
                    'details' => null,
                    'updated_at' => now()->toISOString(),
                    'completed_at' => null
                ];
                
            case 'STREAMING':
                return [
                    'stream_id' => $streamId,
                    'stage' => 'completed',
                    'progress_percentage' => 100,
                    'message' => 'Stream đang phát trực tiếp',
                    'details' => null,
                    'updated_at' => now()->toISOString(),
                    'completed_at' => now()->toISOString()
                ];
                
            case 'ERROR':
                return [
                    'stream_id' => $streamId,
                    'stage' => 'error',
                    'progress_percentage' => 0,
                    'message' => 'Có lỗi xảy ra',
                    'details' => null,
                    'updated_at' => now()->toISOString(),
                    'completed_at' => null
                ];
                
            default:
                return [
                    'stream_id' => $streamId,
                    'stage' => 'idle',
                    'progress_percentage' => 0,
                    'message' => 'Stream không hoạt động',
                    'details' => null,
                    'updated_at' => now()->toISOString(),
                    'completed_at' => null
                ];
        }
    }

    /**
     * Create progress with predefined stage percentages
     */
    public static function createStageProgress(
        int $streamId,
        string $stage,
        string $customMessage = null
    ): bool {
        $stages = [
            'preparing' => ['percentage' => 5, 'message' => 'Đang chuẩn bị stream...'],
            'command_sent' => ['percentage' => 10, 'message' => 'Lệnh đã gửi tới máy chủ...'],
            'validating' => ['percentage' => 15, 'message' => 'Đang kiểm tra cấu hình...'],
            'preparing_video' => ['percentage' => 20, 'message' => 'Đang chuẩn bị video...'],
            'downloading' => ['percentage' => 50, 'message' => 'Đang tải video...'],
            'file_ready' => ['percentage' => 70, 'message' => 'File đã sẵn sàng...'],
            'building_command' => ['percentage' => 75, 'message' => 'Đang xây dựng lệnh stream...'],
            'starting_ffmpeg' => ['percentage' => 80, 'message' => 'Đang khởi động dịch vụ...'],
            'ffmpeg_started' => ['percentage' => 90, 'message' => 'Dịch vụ đã khởi động...'],
            'streaming' => ['percentage' => 100, 'message' => 'Đang phát trực tiếp!'],
            'error' => ['percentage' => 0, 'message' => 'Có lỗi xảy ra']
        ];
        
        if (!isset($stages[$stage])) {
            Log::warning("Invalid progress stage: {$stage}");
            return false;
        }
        
        $stageData = $stages[$stage];
        $message = $customMessage ?: $stageData['message'];
        
        return self::setProgress(
            $streamId,
            $stage,
            $stageData['percentage'],
            $message
        );
    }
}
