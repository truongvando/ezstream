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

        // Vòng lặp vô hạn với cơ chế tự kết nối lại của Laravel Redis
        while (true) {
            try {
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
                $this->error("❌ Redis subscription error: " . $e->getMessage());
                Log::critical('AgentReportListener::handle - Redis subscription failed', ['error' => $e->getMessage()]);
                $this->warn("   Retrying connection in 5 seconds...");
                sleep(5);
            }
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
     * Xử lý báo cáo heartbeat.
     */
    private function handleHeartbeat(array $data): void
    {
        $vpsId = $data['vps_id'] ?? null;
        if (!$vpsId) return;

        $activeStreams = $data['active_streams'] ?? [];
        $this->info("   -> Processing HEARTBEAT from VPS #{$vpsId} with " . count($activeStreams) . " active streams.");

        // Dispatch job để xử lý heartbeat (tránh Redis commands trong subscription context)
        \App\Jobs\ProcessHeartbeatJob::dispatch($vpsId, $activeStreams);
    }

    // --- REMOVED: All DB operations moved to jobs to avoid Redis subscription context issues ---
}
