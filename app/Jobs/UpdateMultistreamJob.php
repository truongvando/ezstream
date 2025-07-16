<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Models\StreamProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Predis\Client as PredisClient;

class UpdateMultistreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private StreamConfiguration $stream;

    /**
     * Create a new job instance.
     */
    public function __construct(StreamConfiguration $stream)
    {
        $this->stream = $stream;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("🔄 [UpdateMultistreamJob] Job started for stream #{$this->stream->id}");

        try {
            // Kiểm tra stream có đang chạy không
            if ($this->stream->status !== 'STREAMING') {
                Log::warning("⚠️ [Stream #{$this->stream->id}] Stream is not STREAMING, cannot update");
                return;
            }

            // Kiểm tra có VPS không
            if (!$this->stream->vps_server_id) {
                Log::error("❌ [Stream #{$this->stream->id}] No VPS assigned, cannot update");
                return;
            }

            // Chuẩn bị video files mới
            $videoFiles = $this->prepareVideoFiles($this->stream);
            if (empty($videoFiles)) {
                throw new \Exception("No video files found for stream update");
            }

            // Tạo config payload mới
            $configPayload = [
                'id' => $this->stream->id,
                'video_files' => $videoFiles,
                'rtmp_url' => $this->stream->rtmp_url,
                'push_urls' => $this->stream->push_urls,
                'loop' => $this->stream->loop ?? true,
                'keep_files_after_stop' => $this->stream->keep_files_after_stop ?? false,
            ];

            // Tạo lệnh UPDATE_STREAM
            $redisCommand = [
                'command' => 'UPDATE_STREAM',
                'config' => $configPayload,
            ];

            // Gửi lệnh tới VPS qua Redis
            $channel = "vps-commands:{$this->stream->vps_server_id}";

            $redisConfig = config('database.redis.default');
            $rawRedis = new PredisClient([
                'scheme' => 'tcp',
                'host' => $redisConfig['host'],
                'port' => $redisConfig['port'],
                'password' => $redisConfig['password'],
                'database' => $redisConfig['database'],
            ]);

            $publishResult = $rawRedis->publish($channel, json_encode($redisCommand));

            if ($publishResult > 0) {
                Log::info("✅ [Stream #{$this->stream->id}] Update command published to Redis channel '{$channel}'", [
                    'publish_result' => $publishResult,
                    'video_files_count' => count($videoFiles)
                ]);

                // Tạo progress record
                StreamProgress::createStageProgress(
                    $this->stream->id,
                    'updating',
                    'Đang cập nhật playlist với ' . count($videoFiles) . ' files...'
                );

            } else {
                throw new \Exception("No agent listening on Redis channel: {$channel}");
            }

        } catch (\Exception $e) {
            Log::error("❌ [UpdateMultistreamJob] Failed for stream #{$this->stream->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Tạo error progress
            StreamProgress::createStageProgress(
                $this->stream->id,
                'error',
                'Lỗi cập nhật stream: ' . $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Prepare video files for agent.py
     */
    private function prepareVideoFiles(StreamConfiguration $stream): array
    {
        $videoSourcePath = $stream->video_source_path;

        if (empty($videoSourcePath) || !is_array($videoSourcePath)) {
            return [];
        }

        $videoFiles = [];

        foreach ($videoSourcePath as $fileInfo) {
            $fileId = $fileInfo['file_id'] ?? null;

            if (!$fileId) {
                continue;
            }

            // Tìm UserFile
            $userFile = \App\Models\UserFile::find($fileId);
            if (!$userFile) {
                Log::warning("UserFile not found: {$fileId}");
                continue;
            }

            // Tạo download URL
            $downloadUrl = null;
            if ($userFile->disk === 'bunny_cdn') {
                $downloadUrl = "https://ezstream.b-cdn.net/{$userFile->path}";
            } elseif ($userFile->disk === 'google_drive') {
                $downloadUrl = "https://drive.google.com/uc?id={$userFile->google_drive_file_id}&export=download";
            }

            if ($downloadUrl) {
                $videoFiles[] = [
                    'file_id' => $userFile->id,
                    'filename' => $userFile->original_name,
                    'download_url' => $downloadUrl,
                    'size' => $userFile->size,
                    'disk' => $userFile->disk,
                ];
            }
        }

        return $videoFiles;
    }
}
