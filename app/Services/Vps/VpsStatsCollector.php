<?php

namespace App\Services\Vps;

use App\Models\VpsServer;
use App\Models\VpsStat;
use App\Models\StreamConfiguration;
use App\Models\StreamProgress;
use Illuminate\Support\Facades\Log;

/**
 * VPS Stats Collector Service
 * Chỉ chịu trách nhiệm: Thu thập và lưu trữ VPS statistics
 */
class VpsStatsCollector
{
    /**
     * Store VPS stats from webhook
     */
    public function storeWebhookStats(array $data): bool
    {
        try {
            // Validate required fields
            if (!isset($data['vps_id'], $data['cpu_usage'], $data['ram_usage'], $data['disk_usage'])) {
                Log::warning('Missing required fields in VPS stats', $data);
                return false;
            }
            
            $vpsId = $data['vps_id'];
            $vps = VpsServer::find($vpsId);
            
            if (!$vps) {
                Log::warning("VPS not found for stats", ['vps_id' => $vpsId]);
                return false;
            }
            
            // Create stats record
            VpsStat::create([
                'vps_server_id' => $vpsId,
                'cpu_usage_percent' => $this->normalizePercentage($data['cpu_usage']),
                'ram_usage_percent' => $this->normalizePercentage($data['ram_usage']),
                'disk_usage_percent' => $this->normalizePercentage($data['disk_usage']),
                'created_at' => isset($data['timestamp']) 
                    ? date('Y-m-d H:i:s', $data['timestamp']) 
                    : now(),
            ]);
            
            // Update VPS server record
            $vps->update([
                'current_streams' => $data['total_streams'] ?? 0,
                'last_seen_at' => now()
            ]);
            
            // Process active streams data if provided
            if (isset($data['active_streams']) && is_array($data['active_streams'])) {
                $this->updateStreamProgress($data['active_streams']);
            }
            
            Log::debug("VPS stats stored successfully", [
                'vps_id' => $vpsId,
                'cpu' => $data['cpu_usage'],
                'ram' => $data['ram_usage'],
                'streams' => $data['total_streams'] ?? 0
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to store VPS stats", [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }
    
    /**
     * Update stream progress from VPS stats
     */
    private function updateStreamProgress(array $activeStreams): void
    {
        foreach ($activeStreams as $streamId => $streamData) {
            try {
                $stream = StreamConfiguration::find($streamId);
                if (!$stream) {
                    continue;
                }
                
                // Update stream progress
                StreamProgress::updateProgress(
                    $streamId,
                    $streamData['status'] ?? 'unknown',
                    $streamData['progress'] ?? 0,
                    $streamData['message'] ?? 'Stream đang chạy...',
                    [
                        'uptime' => $streamData['uptime'] ?? 0,
                        'error' => $streamData['error'] ?? null,
                        'updated_via' => 'vps_stats'
                    ]
                );
                
            } catch (\Exception $e) {
                Log::warning("Failed to update stream progress", [
                    'stream_id' => $streamId,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Normalize percentage values
     */
    private function normalizePercentage($value): float
    {
        return min(100, max(0, floatval($value)));
    }
    
    /**
     * Clean old stats data
     */
    public function cleanOldStats(int $daysToKeep = 7): int
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        $deletedCount = VpsStat::where('created_at', '<', $cutoffDate)->delete();
        
        Log::info("Cleaned old VPS stats", [
            'deleted_records' => $deletedCount,
            'cutoff_date' => $cutoffDate
        ]);
        
        return $deletedCount;
    }
    
    /**
     * Get stats for a specific VPS
     */
    public function getVpsStats(VpsServer $vps, int $hours = 24): array
    {
        $stats = VpsStat::where('vps_server_id', $vps->id)
            ->where('created_at', '>', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->get();
            
        return [
            'vps_id' => $vps->id,
            'vps_name' => $vps->name,
            'period_hours' => $hours,
            'total_records' => $stats->count(),
            'latest_stat' => $stats->first(),
            'average_cpu' => $stats->avg('cpu_usage_percent'),
            'average_ram' => $stats->avg('ram_usage_percent'),
            'average_disk' => $stats->avg('disk_usage_percent'),
            'peak_cpu' => $stats->max('cpu_usage_percent'),
            'peak_ram' => $stats->max('ram_usage_percent'),
            'historical_data' => $stats->take(100)->map(function ($stat) {
                return [
                    'timestamp' => $stat->created_at->timestamp,
                    'cpu' => $stat->cpu_usage_percent,
                    'ram' => $stat->ram_usage_percent,
                    'disk' => $stat->disk_usage_percent
                ];
            })
        ];
    }
    
    /**
     * Get aggregated stats for all VPS
     */
    public function getAggregatedStats(): array
    {
        $recentStats = VpsStat::with('vpsServer')
            ->where('created_at', '>', now()->subHour())
            ->get()
            ->groupBy('vps_server_id');
            
        $summary = [];
        
        foreach ($recentStats as $vpsId => $stats) {
            $vps = $stats->first()->vpsServer;
            $latestStat = $stats->sortByDesc('created_at')->first();
            
            $summary[] = [
                'vps_id' => $vpsId,
                'vps_name' => $vps->name,
                'status' => $latestStat->created_at->gt(now()->subMinutes(5)) ? 'online' : 'offline',
                'current_cpu' => $latestStat->cpu_usage_percent,
                'current_ram' => $latestStat->ram_usage_percent,
                'current_disk' => $latestStat->disk_usage_percent,
                'current_streams' => $vps->current_streams,
                'max_streams' => $vps->max_concurrent_streams,
                'last_update' => $latestStat->created_at,
                'data_points' => $stats->count()
            ];
        }
        
        return $summary;
    }
}
