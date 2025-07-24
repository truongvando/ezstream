<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class StopMultistreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    public function __construct(public StreamConfiguration $stream)
    {
    }

    public function handle(): void
    {
        Log::info("🛑 [StopJob] Handling Stream #{$this->stream->id}");

        try {
            $vpsId = $this->stream->vps_server_id;

            if (!$vpsId) {
                Log::warning("   -> No VPS ID assigned. Marking as INACTIVE directly.", ['stream_id' => $this->stream->id]);
                $this->stream->update(['status' => 'INACTIVE', 'vps_server_id' => null]);
                return;
            }
            
            // Cập nhật trạng thái trong DB trước
            $this->stream->update(['status' => 'STOPPING', 'last_stopped_at' => now()]);

            // Xây dựng và gửi lệnh
            $command = [
                'command' => 'STOP_STREAM',
                'stream_id' => $this->stream->id,
            ];
            
            $channel = "vps-commands:{$vpsId}";
            Redis::publish($channel, json_encode($command));

            Log::info("   -> Sent STOP_STREAM to {$channel}.", ['stream_id' => $this->stream->id]);

        } catch (\Exception $e) {
            $this->failAndSetErrorStatus($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->failAndSetErrorStatus($exception);
    }

    private function failAndSetErrorStatus(\Throwable $exception): void
    {
        Log::error("💥 [StopJob] FAILED for Stream #{$this->stream->id}", ['error' => $exception->getMessage()]);
        // Nếu job thất bại, có thể agent đã chết. Đặt là INACTIVE để tránh treo.
        $this->stream->update([
            'status' => 'INACTIVE',
            'error_message' => "Stop job failed: " . $exception->getMessage(),
            'vps_server_id' => null, // Giải phóng VPS
        ]);
    }
}
