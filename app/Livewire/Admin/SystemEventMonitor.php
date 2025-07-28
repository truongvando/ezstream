<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SystemEventMonitor extends Component
{
    public function render()
    {
        // Get recent agent reports from Redis instead of old webhook events
        $events = $this->getRecentAgentReports();

        return view('livewire.admin.system-event-monitor', [
            'events' => $events,
        ]);
    }

    private function getRecentAgentReports()
    {
        try {
            $reports = [];

            // Get real agent heartbeat reports from Redis
            $this->addHeartbeatReports($reports);

            // Get VPS stats reports (only if they have valid timestamps)
            $this->addVpsStatsReports($reports);

            // Get recent stream status updates from logs
            $this->addRecentStreamEvents($reports);

            // Sort by timestamp and limit to 50 for better visibility
            usort($reports, function($a, $b) {
                return $b->created_at->timestamp - $a->created_at->timestamp;
            });

            return array_slice($reports, 0, 50);

        } catch (\Exception $e) {
            Log::error("Failed to get agent reports: " . $e->getMessage());
            return [];
        }
    }

    private function addHeartbeatReports(&$reports)
    {
        try {
            // Get agent states from Redis (heartbeat data)
            $pattern = 'agent_state:*';
            $keys = Redis::keys($pattern);

            foreach ($keys as $key) {
                $vpsId = str_replace('agent_state:', '', $key);
                $activeStreamsJson = Redis::get($key);
                $ttl = Redis::ttl($key);

                if ($activeStreamsJson && $ttl > 0) {
                    $decoded = json_decode($activeStreamsJson, true);
                    $activeStreams = [];

                    // Extract active_streams from the decoded data
                    if (isset($decoded['active_streams']) && is_array($decoded['active_streams'])) {
                        $activeStreams = $decoded['active_streams'];
                    } else {
                        // Fallback: empty array
                        $activeStreams = [];
                    }

                    $reports[] = (object) [
                        'id' => 'heartbeat_' . $vpsId . '_' . time(),
                        'level' => 'info',
                        'type' => 'AGENT_HEARTBEAT',
                        'message' => "VPS #{$vpsId} heartbeat - " . count($activeStreams) . " active streams",
                        'context' => [
                            'vps_id' => $vpsId,
                            'active_streams' => $activeStreams, // Now guaranteed to be array
                            'stream_count' => count($activeStreams),
                            'ttl_seconds' => $ttl
                        ],
                        'created_at' => Carbon::now()->subSeconds(600 - $ttl) // Estimate when it was received
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to get heartbeat reports: " . $e->getMessage());
        }
    }

    private function addVpsStatsReports(&$reports)
    {
        try {
            $vpsStats = Redis::hgetall('vps_live_stats');
            foreach ($vpsStats as $vpsId => $statsJson) {
                $stats = json_decode($statsJson, true);

                // Only add if we have a valid timestamp and it's recent (within last 5 minutes)
                if ($stats && isset($stats['received_at']) &&
                    (time() - $stats['received_at']) < 300) {

                    $reports[] = (object) [
                        'id' => 'vps_stats_' . $vpsId . '_' . $stats['received_at'],
                        'level' => 'info',
                        'type' => 'VPS_STATS',
                        'message' => "VPS #{$vpsId} system stats",
                        'context' => [
                            'vps_id' => $vpsId,
                            'cpu_usage' => $stats['cpu_usage'] ?? 0,
                            'ram_usage' => $stats['ram_usage'] ?? 0,
                            'disk_usage' => $stats['disk_usage'] ?? 0,
                            'active_streams' => is_array($stats['active_streams'] ?? 0) ? $stats['active_streams'] : [$stats['active_streams'] ?? 0],
                            'stream_count' => is_array($stats['active_streams'] ?? 0) ? count($stats['active_streams']) : ($stats['active_streams'] ?? 0),
                            'network_sent_mb' => $stats['network_sent_mb'] ?? 0,
                            'network_recv_mb' => $stats['network_recv_mb'] ?? 0
                        ],
                        'created_at' => Carbon::createFromTimestamp($stats['received_at'])
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to get VPS stats reports: " . $e->getMessage());
        }
    }

    private function addRecentStreamEvents(&$reports)
    {
        try {
            // Get recent stream status changes from Laravel logs
            $logFile = storage_path('logs/laravel.log');
            if (file_exists($logFile)) {
                $lines = array_slice(file($logFile), -100); // Last 100 lines

                foreach ($lines as $line) {
                    if (strpos($line, 'UpdateStreamStatus') !== false ||
                        strpos($line, 'STREAM_STATUS') !== false) {

                        // Parse log line for stream events
                        if (preg_match('/\[(.*?)\].*stream.*?(\d+)/i', $line, $matches)) {
                            $timestamp = $matches[1] ?? '';
                            $streamId = $matches[2] ?? '';

                            if ($timestamp && $streamId) {
                                try {
                                    $reports[] = (object) [
                                        'id' => 'stream_' . $streamId . '_' . time(),
                                        'level' => 'info',
                                        'type' => 'STREAM_UPDATE',
                                        'message' => "Stream #{$streamId} status updated",
                                        'context' => [
                                            'stream_id' => $streamId,
                                            'log_line' => trim($line)
                                        ],
                                        'created_at' => Carbon::parse($timestamp)
                                    ];
                                } catch (\Exception $e) {
                                    // Skip invalid timestamps
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to parse stream events: " . $e->getMessage());
        }
    }
}
