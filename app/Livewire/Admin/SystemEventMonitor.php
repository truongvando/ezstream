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
            // Get recent agent reports from Redis
            $reports = [];

            // Get VPS stats reports
            $vpsStats = Redis::hgetall('vps_live_stats');
            foreach ($vpsStats as $vpsId => $statsJson) {
                $stats = json_decode($statsJson, true);
                if ($stats && isset($stats['received_at'])) {
                    $reports[] = (object) [
                        'id' => 'vps_' . $vpsId . '_' . $stats['received_at'],
                        'level' => 'info',
                        'type' => 'VPS_STATS',
                        'message' => "VPS #{$vpsId} stats updated",
                        'context' => [
                            'vps_id' => $vpsId,
                            'cpu_usage' => $stats['cpu_usage'] ?? 0,
                            'ram_usage' => $stats['ram_usage'] ?? 0,
                            'active_streams' => $stats['active_streams'] ?? 0
                        ],
                        'created_at' => Carbon::createFromTimestamp($stats['received_at'])
                    ];
                }
            }

            // Get recent stream status updates from logs
            $this->addRecentStreamEvents($reports);

            // Sort by timestamp and limit to 30
            usort($reports, function($a, $b) {
                return $b->created_at->timestamp - $a->created_at->timestamp;
            });

            return array_slice($reports, 0, 30);

        } catch (\Exception $e) {
            Log::error("Failed to get agent reports: " . $e->getMessage());
            return [];
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
