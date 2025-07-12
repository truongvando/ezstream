<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class StopMultistreamJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public StreamConfiguration $stream;

    public function __construct(StreamConfiguration $stream)
    {
        $this->stream = $stream;
        Log::info("🛑 [Stream #{$this->stream->id}] Stop multistream job created");
    }

    public function handle(): void
    {
        Log::info("🛑 [Stream #{$this->stream->id}] Stopping multistream: {$this->stream->title}");

        try {
            // Update stream status
            $this->stream->update(['status' => 'STOPPING']);

            // Check if stream has assigned VPS
            if (!$this->stream->vps_server_id) {
                Log::warning("⚠️ [Stream #{$this->stream->id}] No VPS assigned, marking as stopped");
                $this->stream->update([
                    'status' => 'INACTIVE',
                    'last_stopped_at' => now(),
                ]);
                return;
            }

            $vps = $this->stream->vpsServer;
            if (!$vps) {
                Log::warning("⚠️ [Stream #{$this->stream->id}] VPS not found, marking as stopped");
                $this->stream->update([
                    'status' => 'INACTIVE',
                    'last_stopped_at' => now(),
                ]);
                return;
            }

            // Send stop request to VPS
            $this->sendStreamStopRequest($vps);

            // Update stream status
            $this->stream->update([
                'status' => 'INACTIVE',
                'last_stopped_at' => now(),
                'error_message' => null
            ]);

            // Decrement VPS current streams count
            if ($vps->current_streams > 0) {
                $vps->decrement('current_streams');
            }

            Log::info("✅ [Stream #{$this->stream->id}] Multistream stopped successfully");

        } catch (\Exception $e) {
            Log::error("❌ [Stream #{$this->stream->id}] Stop multistream job failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Still mark as stopped even if VPS communication failed
            $this->stream->update([
                'status' => 'ERROR',
                'error_message' => "Stop failed: {$e->getMessage()}",
                'last_stopped_at' => now(),
            ]);

            throw $e;
        }
    }

    private function sendStreamStopRequest($vps): void
    {
        Log::info("📡 [Stream #{$this->stream->id}] Sending stop request to VPS {$vps->id}");

        $apiUrl = "http://{$vps->ip_address}:9999/stream/stop";
        
        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->retry(3, 2000) // 3 retries with 2 second delay
                ->post($apiUrl, [
                    'stream_id' => $this->stream->id
                ]);

            if (!$response->successful()) {
                throw new \Exception("VPS API request failed: HTTP {$response->status()} - {$response->body()}");
            }

            $responseData = $response->json();
            
            if (isset($responseData['error'])) {
                throw new \Exception("VPS returned error: {$responseData['error']}");
            }

            Log::info("✅ [Stream #{$this->stream->id}] VPS accepted stop request", [
                'vps_response' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error("❌ [Stream #{$this->stream->id}] Failed to send stop request to VPS", [
                'vps_id' => $vps->id,
                'vps_ip' => $vps->ip_address,
                'api_url' => $apiUrl,
                'error' => $e->getMessage()
            ]);

            // Don't throw exception here - we still want to mark stream as stopped
            Log::warning("⚠️ [Stream #{$this->stream->id}] Continuing with stop despite VPS communication failure");
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [Stream #{$this->stream->id}] Stop multistream job failed permanently", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Force stop the stream even if job failed
        $this->stream->update([
            'status' => 'ERROR',
            'error_message' => "Stop failed: {$exception->getMessage()}",
            'last_stopped_at' => now(),
        ]);

        // Decrement VPS stream count
        if ($this->stream->vps_server_id) {
            $vps = $this->stream->vpsServer;
            if ($vps && $vps->current_streams > 0) {
                $vps->decrement('current_streams');
            }
        }
    }
}
