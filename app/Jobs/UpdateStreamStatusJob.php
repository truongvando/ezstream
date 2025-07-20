<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Services\StreamProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateStreamStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    protected array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function handle(): void
    {
        $streamId = $this->data['stream_id'] ?? null;
        $status = $this->data['status'] ?? null;
        $message = $this->data['message'] ?? '';
        $vpsId = $this->data['vps_id'] ?? null;

        if (!$streamId) {
            Log::warning("📨 [UpdateStreamStatus] Missing stream_id in data", $this->data);
            return;
        }

        Log::info("📨 [UpdateStreamStatus] Processing status update for stream #{$streamId}", [
            'status' => $status,
            'message' => $message,
            'vps_id' => $vpsId,
            'full_data' => $this->data
        ]);

        $stream = StreamConfiguration::find($streamId);
        if (!$stream) {
            Log::warning("📨 [UpdateStreamStatus] Stream #{$streamId} not found in database");
            return;
        }

        // Handle different status types
        switch ($status) {
            case 'RUNNING':
            case 'STREAMING':
                $this->handleStreamingStatus($stream, $vpsId, $message);
                break;

            case 'STOPPED':
                $this->handleStoppedStatus($stream, $message);
                break;

            case 'ERROR':
                $this->handleErrorStatus($stream, $message);
                break;

            case 'STARTING':
                $this->handleStartingStatus($stream, $vpsId, $message);
                break;

            default:
                Log::info("📨 [UpdateStreamStatus] Unknown status '{$status}' for stream #{$streamId}");
                break;
        }
    }

    private function handleStreamingStatus(StreamConfiguration $stream, $vpsId, $message): void
    {
        if ($stream->status !== 'STREAMING') {
            Log::info("📨 [UpdateStreamStatus] Stream #{$stream->id} status: {$stream->status} → STREAMING");
            
            $stream->update([
                'status' => 'STREAMING',
                'last_started_at' => now(),
                'last_status_update' => now(),
                'error_message' => null,
                'vps_server_id' => $vpsId
            ]);

            // Increment VPS stream count
            if ($vpsId) {
                VpsServer::find($vpsId)?->increment('current_streams');
            }

            StreamProgressService::createStageProgress($stream->id, 'streaming', 'Stream đang phát trực tiếp!');
        } else {
            // Just update timestamp for existing streaming streams
            $stream->update(['last_status_update' => now()]);
        }
    }

    private function handleStoppedStatus(StreamConfiguration $stream, $message): void
    {
        Log::info("📨 [UpdateStreamStatus] Stream #{$stream->id} status: {$stream->status} → INACTIVE (STOPPED)");
        
        $originalVpsId = $stream->vps_server_id;
        
        $stream->update([
            'status' => 'INACTIVE',
            'last_stopped_at' => now(),
            'last_status_update' => now(),
            'vps_server_id' => null,
            'process_id' => null,
            'error_message' => null
        ]);

        // Decrement VPS stream count
        if ($originalVpsId) {
            VpsServer::find($originalVpsId)?->decrement('current_streams');
        }

        StreamProgressService::createStageProgress($stream->id, 'stopped', $message ?: 'Stream đã dừng');
    }

    private function handleErrorStatus(StreamConfiguration $stream, $message): void
    {
        Log::warning("📨 [UpdateStreamStatus] Stream #{$stream->id} status: {$stream->status} → ERROR");
        
        $originalVpsId = $stream->vps_server_id;
        
        $stream->update([
            'status' => 'ERROR',
            'error_message' => $message,
            'last_status_update' => now(),
            'vps_server_id' => null,
            'process_id' => null
        ]);

        // Decrement VPS stream count
        if ($originalVpsId) {
            VpsServer::find($originalVpsId)?->decrement('current_streams');
        }

        StreamProgressService::createStageProgress($stream->id, 'error', $message ?: 'Stream gặp lỗi');
    }

    private function handleStartingStatus(StreamConfiguration $stream, $vpsId, $message): void
    {
        if ($stream->status !== 'STARTING') {
            Log::info("📨 [UpdateStreamStatus] Stream #{$stream->id} status: {$stream->status} → STARTING");
            
            $stream->update([
                'status' => 'STARTING',
                'last_started_at' => now(),
                'last_status_update' => now(),
                'vps_server_id' => $vpsId,
                'error_message' => null
            ]);

            StreamProgressService::createStageProgress($stream->id, 'starting', $message ?: 'Stream đang khởi động...');
        } else {
            // Just update timestamp
            $stream->update(['last_status_update' => now()]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("📨 [UpdateStreamStatus] Job failed permanently", [
            'data' => $this->data,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
