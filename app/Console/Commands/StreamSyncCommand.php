<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class StreamSyncCommand extends Command
{
    /**
     * Chữ ký của command.
     * --force: Chạy ngay mà không cần confirm.
     * --timeout: Thời gian chờ agent phản hồi (giây).
     */
    protected $signature = 'stream:sync 
                            {--force : Run without confirmation}
                            {--timeout=10 : Seconds to wait for agent heartbeats}';

    /**
     * Mô tả của command.
     */
    protected $description = '[NEW] Syncs stream states between Laravel DB and all active VPS agents.';

    /**
     * Key Redis để lưu trữ tạm thời trạng thái thực tế từ agent.
     */
    private const AGENT_STATE_KEY_PREFIX = 'agent_state:';
    
    /**
     * Thực thi command.
     */
    public function handle()
    {
        $this->info("--------------------------------------------------");
        $this->info("🚀 Starting Stream State Synchronization...");
        $this->info("--------------------------------------------------");

        if (!$this->option('force') && !$this->confirm('This will request state from all agents and fix discrepancies. Continue?')) {
            $this->warn('Operation cancelled by user.');
            return 1;
        }
        
        // 1. Gửi yêu cầu đồng bộ đến tất cả các agent
        $activeVps = VpsServer::where('status', 'ACTIVE')->get();
        if ($activeVps->isEmpty()) {
            $this->info('✅ No active VPS servers found. Nothing to sync.');
            return 0;
        }
        
        $this->line("📡 Sending SYNC_STATE command to {$activeVps->count()} active VPS agents...");
        foreach ($activeVps as $vps) {
            $this->sendCommand($vps->id, 'SYNC_STATE');
            // Xóa trạng thái cũ để đảm bảo dữ liệu mới
            Redis::del(self::AGENT_STATE_KEY_PREFIX . $vps->id);
        }

        // 2. Chờ phản hồi từ agent
        $timeout = (int) $this->option('timeout');
        $this->line("⏳ Waiting {$timeout} seconds for agents to report their heartbeat...");
        sleep($timeout);
        
        // 3. So sánh và xử lý sai lệch
        $this->info("🔍 Analyzing states and fixing discrepancies...");
        $totalMismatches = 0;

        foreach ($activeVps as $vps) {
            $this->line("--- Processing VPS #{$vps->id} ({$vps->name}) ---");
            
            // Lấy trạng thái DB
            $dbStreams = StreamConfiguration::where('vps_server_id', $vps->id)
                ->whereIn('status', ['STREAMING', 'STARTING'])
                ->pluck('id')
                ->collect();

            // Lấy trạng thái thực tế từ agent (qua heartbeat)
            $agentStateJson = Redis::get(self::AGENT_STATE_KEY_PREFIX . $vps->id);
            $agentStreams = collect($agentStateJson ? json_decode($agentStateJson, true) : []);

            $this->info("  Database expects: " . ($dbStreams->isEmpty() ? 'None' : $dbStreams->implode(', ')));
            $this->info("  Agent actually has: " . ($agentStreams->isEmpty() ? 'None' : $agentStreams->implode(', ')));

            // Tìm stream có trong DB nhưng agent không chạy (Stream mất tích)
            $missingStreams = $dbStreams->diff($agentStreams);
            if ($missingStreams->isNotEmpty()) {
                $totalMismatches += $missingStreams->count();
                $this->warn("  ❗️ MISMATCH (Missing): Streams " . $missingStreams->implode(', ') . " should be running, but are not.");
                foreach ($missingStreams as $streamId) {
                    $this->fixMissingStream($streamId);
                }
            }
            
            // Tìm stream agent đang chạy nhưng không có trong DB hoặc status sai (Stream mồ côi)
            $orphanStreams = $agentStreams->diff($dbStreams);
            if ($orphanStreams->isNotEmpty()) {
                $totalMismatches += $orphanStreams->count();
                $this->warn("  ❗️ MISMATCH (Orphaned): Streams " . $orphanStreams->implode(', ') . " are running on agent, but DB says otherwise.");
                foreach ($orphanStreams as $streamId) {
                    $this->fixOrphanedStream($vps->id, $streamId);
                }
            }

            if ($missingStreams->isEmpty() && $orphanStreams->isEmpty()) {
                $this->info("  ✅ State is consistent.");
            }
        }
        
        $this->info("--------------------------------------------------");
        if ($totalMismatches > 0) {
            $this->info("🎉 Sync completed. Fixed {$totalMismatches} discrepancies.");
        } else {
            $this->info("🎉 Sync completed. All systems are consistent.");
        }
        $this->info("--------------------------------------------------");

        return 0;
    }

    /**
     * Gửi một lệnh đến một VPS cụ thể.
     */
    private function sendCommand(int $vpsId, string $command, array $payload = []): void
    {
        try {
            $channel = "vps-commands:{$vpsId}";
            $fullPayload = json_encode(array_merge(['command' => $command], $payload));
            Redis::publish($channel, $fullPayload);
        } catch (\Exception $e) {
            $this->error("  ❌ Failed to send command '{$command}' to VPS #{$vpsId}: {$e->getMessage()}");
        }
    }

    /**
     * Xử lý stream bị mất tích (có trong DB, agent không có).
     */
    private function fixMissingStream(int $streamId): void
    {
        $stream = StreamConfiguration::find($streamId);
        if ($stream) {
            $stream->update([
                'status' => 'ERROR',
                'error_message' => 'Sync Error: Stream process lost on agent. Needs restart.',
                'vps_server_id' => null, // Giải phóng VPS
            ]);
            Log::warning("StreamSync: Marked stream #{$streamId} as ERROR (missing from agent).");
            $this->line("    -> Fixed stream #{$streamId}: Set status to ERROR.");
        }
    }

    /**
     * Xử lý stream mồ côi (agent có, DB không có).
     */
    private function fixOrphanedStream(int $vpsId, int $streamId): void
    {
        $stream = StreamConfiguration::find($streamId);
        // Nếu stream không tồn tại trong DB, hoặc tồn tại nhưng không nên chạy -> Gửi lệnh STOP
        if (!$stream || !in_array($stream->status, ['STREAMING', 'STARTING'])) {
            $this->sendCommand($vpsId, 'STOP_STREAM', ['stream_id' => $streamId]);
            Log::warning("StreamSync: Sent STOP command for orphaned stream #{$streamId} on VPS #{$vpsId}.");
            $this->line("    -> Fixed stream #{$streamId}: Sent STOP command to agent.");
        }
    }
}
