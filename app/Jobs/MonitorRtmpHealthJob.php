<?php

namespace App\Jobs;

use App\Models\StreamConfiguration;
use App\Services\RtmpCircuitBreaker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorRtmpHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public function handle(): void
    {
        Log::info("🔍 [RtmpHealth] Starting RTMP health monitoring");

        $this->checkActiveRtmpServers();
        $this->checkCircuitBreakerStates();
        $this->handleFailedStreams();

        Log::info("✅ [RtmpHealth] RTMP health monitoring completed");
    }

    /**
     * Check health of RTMP servers currently in use
     */
    private function checkActiveRtmpServers(): void
    {
        $activeStreams = StreamConfiguration::whereIn('status', ['STREAMING', 'STARTING'])
            ->whereNotNull('rtmp_url')
            ->select('rtmp_url')
            ->distinct()
            ->get();

        $checkedServers = [];

        foreach ($activeStreams as $stream) {
            $rtmpUrl = $this->extractBaseRtmpUrl($stream->rtmp_url);
            
            if (in_array($rtmpUrl, $checkedServers)) {
                continue; // Already checked this server
            }

            Log::debug("🔍 [RtmpHealth] Checking RTMP server: {$rtmpUrl}");
            
            $isHealthy = RtmpCircuitBreaker::healthCheck($rtmpUrl);
            $checkedServers[] = $rtmpUrl;

            if ($isHealthy) {
                Log::debug("✅ [RtmpHealth] RTMP server healthy: {$rtmpUrl}");
            } else {
                Log::warning("❌ [RtmpHealth] RTMP server unhealthy: {$rtmpUrl}");
            }
        }

        Log::info("📊 [RtmpHealth] Checked {count($checkedServers)} unique RTMP servers");
    }

    /**
     * Check circuit breaker states and log status
     */
    private function checkCircuitBreakerStates(): void
    {
        $statuses = RtmpCircuitBreaker::getAllStatuses();
        
        foreach ($statuses as $rtmpUrl => $status) {
            if ($status['state'] !== 'CLOSED') {
                Log::warning("⚠️ [RtmpHealth] Circuit breaker {$status['state']} for {$rtmpUrl} (failures: {$status['failure_count']})");
                
                if ($status['state'] === 'OPEN' && $status['next_retry']) {
                    $retryIn = $status['next_retry'] - time();
                    Log::info("⏰ [RtmpHealth] Next retry for {$rtmpUrl} in {$retryIn} seconds");
                }
            }
        }
    }

    /**
     * Handle streams affected by failed RTMP servers
     */
    private function handleFailedStreams(): void
    {
        $failedStreams = StreamConfiguration::whereIn('status', ['STREAMING', 'STARTING'])
            ->whereNotNull('rtmp_url')
            ->get()
            ->filter(function ($stream) {
                $rtmpUrl = $this->extractBaseRtmpUrl($stream->rtmp_url);
                return !RtmpCircuitBreaker::isAvailable($rtmpUrl);
            });

        foreach ($failedStreams as $stream) {
            $rtmpUrl = $this->extractBaseRtmpUrl($stream->rtmp_url);
            
            Log::warning("🚨 [RtmpHealth] Stream #{$stream->id} affected by failed RTMP server: {$rtmpUrl}");
            
            // Try to find alternative RTMP server
            $alternatives = RtmpCircuitBreaker::getAlternativeServers($rtmpUrl);
            
            if (!empty($alternatives)) {
                $newRtmpUrl = $alternatives[0];
                Log::info("🔄 [RtmpHealth] Switching stream #{$stream->id} to alternative RTMP: {$newRtmpUrl}");
                
                // Update stream with alternative RTMP URL
                $newFullUrl = str_replace($this->extractBaseRtmpUrl($stream->rtmp_url), $newRtmpUrl, $stream->rtmp_url);
                
                $stream->update([
                    'rtmp_url' => $newFullUrl,
                    'error_message' => "Switched to alternative RTMP server due to primary server failure"
                ]);

                // Restart stream with new RTMP URL
                \App\Jobs\StopMultistreamJob::dispatch($stream->id, 'RTMP server switch');
                \App\Jobs\StartMultistreamJob::dispatch($stream->id)->delay(now()->addSeconds(10));

                \App\Services\StreamProgressService::createStageProgress(
                    $stream->id,
                    'warning',
                    "🔄 Switched to alternative RTMP server"
                );
            } else {
                Log::error("❌ [RtmpHealth] No alternative RTMP servers available for stream #{$stream->id}");
                
                $stream->update([
                    'status' => 'ERROR',
                    'error_message' => 'RTMP server unavailable and no alternatives found'
                ]);

                \App\Services\StreamProgressService::createStageProgress(
                    $stream->id,
                    'error',
                    "❌ RTMP server unavailable - no alternatives"
                );
            }
        }

        if ($failedStreams->count() > 0) {
            Log::warning("⚠️ [RtmpHealth] Handled {$failedStreams->count()} streams affected by RTMP failures");
        }
    }

    /**
     * Extract base RTMP URL (without stream key)
     */
    private function extractBaseRtmpUrl(string $fullRtmpUrl): string
    {
        // Remove stream key from RTMP URL
        $parts = explode('/', $fullRtmpUrl);
        
        if (count($parts) >= 4) {
            // Keep protocol, host, and path, remove stream key
            return implode('/', array_slice($parts, 0, 4));
        }
        
        return $fullRtmpUrl;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("❌ [RtmpHealth] Monitoring job failed: " . $exception->getMessage());
    }
}
