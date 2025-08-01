<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Services\StreamProgressService;

class StreamStatusListener extends Command
{
    /**
     * Tên command mới, thể hiện rõ vai trò là Listener Báo Cáo.
     */
    protected $signature = 'agent:listen';

    /**
     * Mô tả mới.
     */
    protected $description = '[NEW] Listens for all agent reports (status, heartbeat) from the central Redis channel.';

    /**
     * Kênh Redis chung để nhận báo cáo.
     */
    private const AGENT_REPORTS_CHANNEL = 'agent-reports';

    /**
     * Prefix cho key lưu trạng thái heartbeat của agent.
     */
    private const AGENT_STATE_KEY_PREFIX = 'agent_state:';

    /**
     * Thực thi command.
     */
    public function handle()
    {
        $this->info("--------------------------------------------------");
        $this->info("🎧 Starting Agent Report Listener...");
        $this->info("   Listening on channel: " . self::AGENT_REPORTS_CHANNEL);
        $this->info("--------------------------------------------------");

        $retryCount = 0;
        $maxRetries = 10;
        $baseDelay = 5;

        // Vòng lặp vô hạn với cơ chế tự kết nối lại của Laravel Redis
        while (true) {
            try {
                // Reset retry count on successful connection
                $retryCount = 0;

                // Test connection before subscribing
                $this->testRedisConnection();
                $this->info("✅ Redis connection verified, starting subscription...");

                Redis::subscribe([self::AGENT_REPORTS_CHANNEL], function (string $message, string $channel) {
                    $this->line("📨 Received on [{$channel}]");

                    $data = json_decode($message, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $this->warn("   ⚠️ Invalid JSON received. Payload: {$message}");
                        return;
                    }

                    $this->processReport($data);
                });
            } catch (\Exception $e) {
                $retryCount++;

                $this->error("❌ Redis subscription error (attempt {$retryCount}/{$maxRetries}): " . $e->getMessage());
                Log::critical('AgentReportListener::handle - Redis subscription failed', [
                    'error' => $e->getMessage(),
                    'attempt' => $retryCount,
                    'max_retries' => $maxRetries
                ]);

                if ($retryCount >= $maxRetries) {
                    $this->error("💀 Max retries reached. Exiting...");
                    return 1;
                }

                // Exponential backoff with jitter
                $delay = min($baseDelay * pow(2, $retryCount - 1), 60) + rand(1, 5);
                $this->warn("   Retrying connection in {$delay} seconds...");

                // Clear Redis connection cache
                try {
                    Redis::purge('default');
                } catch (\Exception $purgeException) {
                    $this->warn("   Failed to purge Redis connection: " . $purgeException->getMessage());
                }

                sleep($delay);
            }
        }
    }

    /**
     * Test Redis connection
     */
    private function testRedisConnection(): void
    {
        $redis = Redis::connection('default');
        $result = $redis->ping();

        $isHealthy = $result === 'PONG' ||
                    $result === true ||
                    (is_object($result) && method_exists($result, '__toString') && (string)$result === 'PONG');

        if (!$isHealthy) {
            throw new \Exception("Redis ping failed: " . json_encode($result));
        }
    }

    /**
     * Xử lý báo cáo nhận được.
     */
    private function processReport(array $data): void
    {
        $type = $data['type'] ?? 'UNKNOWN';

        switch ($type) {
            case 'STATUS_UPDATE':
                $this->handleStatusUpdate($data);
                break;
            case 'RESTART_REQUEST':
                $this->handleRestartRequest($data);
                break;
            case 'HEARTBEAT':
                $this->handleHeartbeat($data);
                break;

            default:
                $this->warn("   - Unhandled report type: '{$type}'");
                break;
        }
    }

    /**
     * Xử lý báo cáo cập nhật trạng thái.
     */
    private function handleStatusUpdate(array $data): void
    {
        if (!isset($data['stream_id'])) {
            $this->warn("   ⚠️ STATUS_UPDATE missing stream_id");
            return;
        }

        $status = $data['status'] ?? 'UNKNOWN';
        $message = $data['message'] ?? '';
        $vpsId = $data['vps_id'] ?? 'N/A';

        $this->info("   -> Processing STATUS_UPDATE for Stream #{$data['stream_id']}, Status: {$status}, VPS: {$vpsId}");
        $this->info("   -> Message: {$message}");
        $this->info("   -> Full data: " . json_encode($data, JSON_PRETTY_PRINT));

        // Dispatch job để xử lý status update (tránh DB operations trong subscription context)
        \App\Jobs\UpdateStreamStatusJob::dispatch($data);
    }

    /**
     * Xử lý restart request từ Agent
     */
    private function handleRestartRequest(array $data): void
    {
        $streamId = $data['stream_id'] ?? null;
        $vpsId = $data['vps_id'] ?? null;
        $reason = $data['reason'] ?? 'Unknown reason';
        $crashCount = $data['crash_count'] ?? 1;
        $errorType = $data['error_type'] ?? null;
        $lastError = $data['last_error'] ?? null;

        $this->warn("   -> 🔄 RESTART_REQUEST: Stream #{$streamId} crashed #{$crashCount} times - {$reason}");

        if ($errorType) {
            $this->line("      Error type: {$errorType}");
        }

        // Dispatch job để xử lý restart request
        \App\Jobs\ProcessRestartRequestJob::dispatch($streamId, $vpsId, $reason, $crashCount, $errorType, $lastError);
    }

    /**
     * Xử lý báo cáo heartbeat.
     */
    private function handleHeartbeat(array $data): void
    {
        $vpsId = $data['vps_id'] ?? null;
        if (!$vpsId) return;

        $activeStreams = $data['active_streams'] ?? [];
        $isReAnnounce = $data['re_announce'] ?? false;
        $isImmediateUpdate = $data['immediate_update'] ?? false;

        if ($isImmediateUpdate) {
            $this->info("   -> ⚡ IMMEDIATE HEARTBEAT from VPS #{$vpsId} with " . count($activeStreams) . " active streams (stream state changed)");
        } elseif ($isReAnnounce) {
            $this->warn("   -> 🔄 RE-ANNOUNCE HEARTBEAT from VPS #{$vpsId} with " . count($activeStreams) . " active streams (potential restart recovery)");
        } else {
            $this->info("   -> Processing HEARTBEAT from VPS #{$vpsId} with " . count($activeStreams) . " active streams.");
        }

        // Log active streams for debugging
        if (!empty($activeStreams)) {
            $this->line("      Active streams: " . implode(', ', $activeStreams));
        }

        // Dispatch job để xử lý heartbeat (tránh Redis commands trong subscription context)
        \App\Jobs\ProcessHeartbeatJob::dispatch($vpsId, $activeStreams, $isReAnnounce, $isImmediateUpdate);
    }



    // --- REMOVED: All DB operations moved to jobs to avoid Redis subscription context issues ---
}
