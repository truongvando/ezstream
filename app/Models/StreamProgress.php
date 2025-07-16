<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class StreamProgress extends Model
{
    use HasFactory;

    protected $table = 'stream_progress';

    protected $fillable = [
        'stream_configuration_id',
        'stage',
        'progress_percentage',
        'message',
        'details',
        'completed_at'
    ];

    protected $casts = [
        'details' => 'array',
        'completed_at' => 'datetime',
        'progress_percentage' => 'integer'
    ];

    /**
     * Relationship with StreamConfiguration
     */
    public function streamConfiguration()
    {
        return $this->belongsTo(StreamConfiguration::class);
    }

    /**
     * Update or create progress for a stream
     */
    public static function updateProgress(
        int $streamId,
        string $stage,
        int $progressPercentage,
        string $message,
        array $details = null
    ): self {
        try {
            // Validate progress percentage
            $progressPercentage = max(0, min(100, $progressPercentage));
            
            // Create new progress record
            $progress = self::create([
                'stream_configuration_id' => $streamId,
                'stage' => $stage,
                'progress_percentage' => $progressPercentage,
                'message' => $message,
                'details' => $details,
                'completed_at' => $progressPercentage >= 100 ? now() : null
            ]);

            Log::info("Stream progress updated", [
                'stream_id' => $streamId,
                'stage' => $stage,
                'progress' => $progressPercentage,
                'message' => $message
            ]);

            return $progress;

        } catch (\Exception $e) {
            Log::error("Failed to update stream progress", [
                'stream_id' => $streamId,
                'stage' => $stage,
                'progress' => $progressPercentage,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get latest progress for a stream
     */
    public static function getLatestProgress(int $streamId): ?self
    {
        return self::where('stream_configuration_id', $streamId)
            ->latest()
            ->first();
    }

    /**
     * Clear all progress for a stream
     */
    public static function clearProgress(int $streamId): bool
    {
        try {
            self::where('stream_configuration_id', $streamId)->delete();
            
            Log::info("Stream progress cleared", [
                'stream_id' => $streamId
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to clear stream progress", [
                'stream_id' => $streamId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get progress stages with default percentages
     */
    public static function getProgressStages(): array
    {
        return [
            // Laravel stages (initial)
            'preparing' => [
                'percentage' => 5,
                'message' => 'Đang chuẩn bị stream...'
            ],
            'command_sent' => [
                'percentage' => 10,
                'message' => 'Lệnh đã gửi tới VPS...'
            ],

            // Agent.py stages (real progress)
            'validating' => [
                'percentage' => 15,
                'message' => 'Đang kiểm tra cấu hình...'
            ],
            'preparing_video' => [
                'percentage' => 20,
                'message' => 'Đang chuẩn bị video input...'
            ],
            'checking_file' => [
                'percentage' => 25,
                'message' => 'Đang kiểm tra file...'
            ],
            'testing_url' => [
                'percentage' => 30,
                'message' => 'Đang kiểm tra khả năng truy cập...'
            ],
            'url_ready' => [
                'percentage' => 35,
                'message' => 'File sẵn sàng...'
            ],
            // Download progress (dynamic percentage from agent)
            'downloading' => [
                'percentage' => 50, // This will be overridden by agent.py
                'message' => 'Đang tải video...'
            ],
            'file_ready' => [
                'percentage' => 70,
                'message' => 'File đã sẵn sàng...'
            ],
            'creating_playlist' => [
                'percentage' => 45,
                'message' => 'Đang tạo playlist...'
            ],
            'playlist_ready' => [
                'percentage' => 60,
                'message' => 'Playlist đã sẵn sàng...'
            ],
            'building_command' => [
                'percentage' => 70,
                'message' => 'Đang xây dựng lệnh stream...'
            ],
            'starting_ffmpeg' => [
                'percentage' => 80,
                'message' => 'Đang khởi động dịch vụ...'
            ],
            'ffmpeg_started' => [
                'percentage' => 90,
                'message' => 'Dịch vụ đã khởi động, đang kết nối RTMP...'
            ],
            'streaming' => [
                'percentage' => 95,
                'message' => 'Đang bắt đầu phát...'
            ],
            'completed' => [
                'percentage' => 100,
                'message' => 'Stream đang phát trực tiếp!'
            ],
            'error' => [
                'percentage' => 0,
                'message' => 'Có lỗi xảy ra'
            ]
        ];
    }

    /**
     * Create progress with predefined stage
     */
    public static function createStageProgress(
        int $streamId,
        string $stage,
        string $customMessage = null,
        array $details = null
    ): self {
        $stages = self::getProgressStages();
        
        if (!isset($stages[$stage])) {
            throw new \InvalidArgumentException("Invalid progress stage: {$stage}");
        }
        
        $stageData = $stages[$stage];
        $message = $customMessage ?: $stageData['message'];
        
        return self::updateProgress(
            $streamId,
            $stage,
            $stageData['percentage'],
            $message,
            $details
        );
    }
}
