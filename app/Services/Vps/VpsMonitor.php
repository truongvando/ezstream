<?php

namespace App\Services\Vps;

use App\Models\VpsServer;
use App\Models\VpsStat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * VPS Monitor Service
 * Chỉ chịu trách nhiệm: Monitor VPS health và availability
 */
class VpsMonitor
{
    /**
     * Check if VPS is healthy and available
     */
    public function isHealthy(VpsServer $vps): bool
    {
        // Check if VPS has recent stats (within 5 minutes)
        $latestStat = $vps->latestStat;
        
        if (!$latestStat || $latestStat->created_at->lt(now()->subMinutes(5))) {
            return false;
        }
        
        // Check resource thresholds
        return $latestStat->cpu_usage_percent < 90 
            && $latestStat->ram_usage_percent < 95 
            && $latestStat->disk_usage_percent < 95;
    }
    
    /**
     * Get VPS health status
     */
    public function getHealthStatus(VpsServer $vps): array
    {
        $latestStat = $vps->latestStat;
        
        if (!$latestStat) {
            return [
                'status' => 'unknown',
                'message' => 'No stats available',
                'last_seen' => null
            ];
        }
        
        $lastSeen = $latestStat->created_at;
        $isOnline = $lastSeen->gt(now()->subMinutes(5));
        
        if (!$isOnline) {
            return [
                'status' => 'offline',
                'message' => 'No recent data',
                'last_seen' => $lastSeen
            ];
        }
        
        // Check if overloaded
        if ($latestStat->cpu_usage_percent > 90 || $latestStat->ram_usage_percent > 95) {
            return [
                'status' => 'overloaded',
                'message' => 'High resource usage',
                'last_seen' => $lastSeen,
                'cpu' => $latestStat->cpu_usage_percent,
                'ram' => $latestStat->ram_usage_percent
            ];
        }
        
        return [
            'status' => 'healthy',
            'message' => 'Operating normally',
            'last_seen' => $lastSeen,
            'cpu' => $latestStat->cpu_usage_percent,
            'ram' => $latestStat->ram_usage_percent,
            'disk' => $latestStat->disk_usage_percent
        ];
    }
    
    /**
     * Get all VPS with their health status
     */
    public function getAllVpsHealth(): Collection
    {
        return VpsServer::where('status', 'ACTIVE')
            ->with('latestStat')
            ->get()
            ->map(function ($vps) {
                return [
                    'vps' => $vps,
                    'health' => $this->getHealthStatus($vps),
                    'is_healthy' => $this->isHealthy($vps)
                ];
            });
    }
    
    /**
     * Check if VPS has webhook agent running
     */
    public function hasActiveWebhookAgent(VpsServer $vps): bool
    {
        // Check for recent webhook data (within 2 minutes)
        return VpsStat::where('vps_server_id', $vps->id)
            ->where('created_at', '>', now()->subMinutes(2))
            ->exists();
    }
    
    /**
     * Get VPS capacity info
     */
    public function getCapacityInfo(VpsServer $vps): array
    {
        return [
            'current_streams' => $vps->current_streams ?? 0,
            'max_streams' => $vps->max_concurrent_streams ?? 0,
            'available_capacity' => max(0, ($vps->max_concurrent_streams ?? 0) - ($vps->current_streams ?? 0)),
            'utilization_percent' => $vps->max_concurrent_streams > 0 
                ? round(($vps->current_streams / $vps->max_concurrent_streams) * 100, 1)
                : 0
        ];
    }
    
    /**
     * Find VPS that need attention
     */
    public function getVpsNeedingAttention(): Collection
    {
        return $this->getAllVpsHealth()
            ->filter(function ($item) {
                return !$item['is_healthy'] || $item['health']['status'] !== 'healthy';
            });
    }
    
    /**
     * Get VPS statistics summary
     */
    public function getStatsSummary(): array
    {
        $allVps = $this->getAllVpsHealth();
        
        return [
            'total_vps' => $allVps->count(),
            'healthy_vps' => $allVps->where('is_healthy', true)->count(),
            'offline_vps' => $allVps->where('health.status', 'offline')->count(),
            'overloaded_vps' => $allVps->where('health.status', 'overloaded')->count(),
            'total_capacity' => $allVps->sum('vps.max_concurrent_streams'),
            'used_capacity' => $allVps->sum('vps.current_streams'),
            'webhook_active' => $allVps->filter(function ($item) {
                return $this->hasActiveWebhookAgent($item['vps']);
            })->count()
        ];
    }
}
