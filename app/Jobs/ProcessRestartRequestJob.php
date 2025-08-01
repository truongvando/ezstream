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

class ProcessRestartRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public $tries = 3;

    public function __construct(
        public int $streamId,
        public int $vpsId,
        public string $reason,
        public int $crashCount,
        public ?string $errorType = null,
        public ?string $lastError = null
    ) {}

    public function handle(): void
    {
        try {
            Log::info("🔄 [ProcessRestartRequest] Handling restart request for stream #{$this->streamId}", [
                'vps_id' => $this->vpsId,
                'crash_count' => $this->crashCount,
                'reason' => $this->reason,
                'error_type' => $this->errorType
            ]);

            $stream = StreamConfiguration::find($this->streamId);
            
            if (!$stream) {
                Log::warning("🔄 [ProcessRestartRequest] Stream #{$this->streamId} not found in database");
                return;
            }

            // Laravel decides whether to restart based on business logic
            $shouldRestart = $this->shouldRestartStream($stream);

            if ($shouldRestart) {
                Log::info("🔄 [ProcessRestartRequest] Laravel decides to RESTART stream #{$this->streamId}");

                // ⏱️ Wait 3 seconds for process cleanup and resource release
                Log::info("⏱️ [ProcessRestartRequest] Waiting 3s for process cleanup before restart...");
                sleep(3);

                // Update stream status and dispatch restart
                $stream->update([
                    'status' => 'STARTING',
                    'error_message' => null,
                    'last_started_at' => now()
                ]);

                // Send START_STREAM command to Agent
                $this->sendStartCommand($stream);

                // Create progress update
                \App\Services\StreamProgressService::createStageProgress(
                    $this->streamId,
                    'starting',
                    "🔄 Laravel auto-restart after crash #{$this->crashCount}: {$this->reason}"
                );

            } else {
                Log::warning("🔄 [ProcessRestartRequest] Laravel decides to STOP stream #{$this->streamId}");
                
                // Mark as ERROR and disable auto-restart
                $stream->update([
                    'status' => 'ERROR',
                    'error_message' => "Too many crashes ({$this->crashCount}): {$this->reason}",
                    'enable_schedule' => false, // Disable schedule to prevent further restarts
                    'vps_server_id' => null
                ]);

                // Create progress update
                \App\Services\StreamProgressService::createStageProgress(
                    $this->streamId,
                    'error',
                    "❌ Stream disabled after {$this->crashCount} crashes: {$this->reason}"
                );
            }

        } catch (\Exception $e) {
            Log::error("❌ [ProcessRestartRequest] Failed to process restart request: {$e->getMessage()}", [
                'stream_id' => $this->streamId,
                'exception' => $e->getTraceAsString()
            ]);
        }
    }

    private function shouldRestartStream(StreamConfiguration $stream): bool
    {
        // Business logic for restart decision

        // 0. Check if stream is already being restarted (prevent conflicts)
        if ($stream->status === 'STARTING') {
            $minutesSinceStart = $stream->last_started_at ? now()->diffInMinutes($stream->last_started_at) : 999;
            if ($minutesSinceStart < 2) {
                Log::info("🔄 [RestartDecision] Stream #{$this->streamId} already restarting (started {$minutesSinceStart}m ago) - avoiding conflict");
                return false;
            }
        }

        // 1. Check if stream has schedule enabled
        if (!$stream->enable_schedule) {
            Log::info("🔄 [RestartDecision] Stream #{$this->streamId} has schedule disabled");
            return false;
        }

        // 2. Check crash count limit
        $maxCrashes = 3; // Maximum crashes before giving up
        if ($this->crashCount >= $maxCrashes) {
            Log::warning("🔄 [RestartDecision] Stream #{$this->streamId} exceeded max crashes ({$this->crashCount}/{$maxCrashes})");
            return false;
        }

        // 3. Check for permanent errors
        $permanentErrors = ['FILE_NOT_FOUND', 'PERMISSION_ERROR', 'CORRUPTED_FILE', 'OUT_OF_MEMORY'];
        if ($this->errorType && in_array($this->errorType, $permanentErrors)) {
            Log::warning("🔄 [RestartDecision] Stream #{$this->streamId} has permanent error: {$this->errorType}");
            return false;
        }

        // 4. Check recent restart frequency (prevent spam) - Increased to 10 seconds for process cleanup
        $recentRestarts = $stream->updated_at && $stream->updated_at->diffInSeconds(now()) < 10;
        if ($recentRestarts && $this->crashCount > 1) {
            Log::warning("🔄 [RestartDecision] Stream #{$this->streamId} restarted too recently (< 10s ago)");
            return false;
        }

        // 5. Check user subscription status
        if ($stream->user && !$stream->user->hasActiveSubscription()) {
            Log::info("🔄 [RestartDecision] Stream #{$this->streamId} user has no active subscription");
            return false;
        }

        Log::info("🔄 [RestartDecision] Stream #{$this->streamId} approved for restart");
        return true;
    }

    private function sendStartCommand(StreamConfiguration $stream): void
    {
        try {
            // Build start command (same as StartMultistreamJob)
            $command = [
                'command' => 'START_STREAM',
                'stream_id' => $stream->id,
                'config' => [
                    'stream_id' => $stream->id,
                    'title' => $stream->title,
                    'input_path' => $stream->input_path,
                    'rtmp_endpoints' => $stream->rtmp_endpoints,
                    'enable_schedule' => $stream->enable_schedule,
                    'scheduled_at' => $stream->scheduled_at?->toISOString(),
                ]
            ];

            $channel = "vps-commands:{$this->vpsId}";
            $result = Redis::publish($channel, json_encode($command));

            Log::info("📤 [ProcessRestartRequest] Sent START_STREAM command to VPS #{$this->vpsId} (subscribers: {$result})");

        } catch (\Exception $e) {
            Log::error("❌ [ProcessRestartRequest] Failed to send START command: {$e->getMessage()}");
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("❌ [ProcessRestartRequest] Job failed for stream #{$this->streamId}: {$exception->getMessage()}");
        
        // Mark stream as ERROR if job fails
        try {
            $stream = StreamConfiguration::find($this->streamId);
            if ($stream) {
                $stream->update([
                    'status' => 'ERROR',
                    'error_message' => "Restart request processing failed: {$exception->getMessage()}"
                ]);
            }
        } catch (\Exception $e) {
            Log::error("❌ [ProcessRestartRequest] Failed to update stream status after job failure: {$e->getMessage()}");
        }
    }
}
