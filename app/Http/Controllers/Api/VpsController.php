<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VpsServer;
use App\Models\StreamConfiguration;
use App\Services\Vps\VpsMonitor;
use App\Services\Vps\VpsStatsCollector;
use App\Services\Vps\VpsConnection;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VpsController extends Controller
{
    private VpsMonitor $vpsMonitor;
    private VpsStatsCollector $statsCollector;
    private VpsConnection $vpsConnection;

    public function __construct(
        VpsMonitor $vpsMonitor,
        VpsStatsCollector $statsCollector,
        VpsConnection $vpsConnection
    ) {
        $this->vpsMonitor = $vpsMonitor;
        $this->statsCollector = $statsCollector;
        $this->vpsConnection = $vpsConnection;
    }

    /**
     * Get health overview of all VPS servers
     * GET /api/public/vps/health-overview
     */
    public function healthOverview(): JsonResponse
    {
        try {
            $vpsMonitor = app(\App\Services\Vps\VpsMonitor::class);
            $allVpsHealth = $vpsMonitor->getAllVpsHealth();

            $summary = [
                'total_vps' => $allVpsHealth->count(),
                'healthy_vps' => $allVpsHealth->where('is_healthy', true)->count(),
                'unhealthy_vps' => $allVpsHealth->where('is_healthy', false)->count(),
                'total_capacity' => $allVpsHealth->sum(fn($item) => $item['vps']->max_concurrent_streams ?? 0),
                'used_capacity' => $allVpsHealth->sum(fn($item) => $item['vps']->current_streams ?? 0),
            ];

            $vps_list = $allVpsHealth->map(function($item) {
                $vps = $item['vps'];
                $health = $item['health'];
                return [
                    'id' => $vps->id,
                    'name' => $vps->name,
                    'ip_address' => $vps->ip_address,
                    'status' => $health['status'],
                    'is_healthy' => $item['is_healthy'],
                    'last_seen' => $health['last_seen']?->toISOString(),
                    'cpu_usage' => $health['cpu'] ?? 0,
                    'ram_usage' => $health['ram'] ?? 0,
                    'disk_usage' => $health['disk'] ?? 0,
                    'current_streams' => $vps->current_streams ?? 0,
                    'max_streams' => $vps->max_concurrent_streams ?? 0,
                    'message' => $health['message']
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'vps_list' => $vps_list
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting VPS health overview: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get VPS health overview'
            ], 500);
        }
    }

    /**
     * Get aggregated stats for all VPS servers
     * GET /api/public/vps/aggregated-stats
     */
    public function aggregatedStats(): JsonResponse
    {
        try {
            $vpsStatsCollector = app(\App\Services\Vps\VpsStatsCollector::class);
            $aggregatedStats = $vpsStatsCollector->getAggregatedStats();

            return response()->json([
                'success' => true,
                'data' => $aggregatedStats
            ]);

        } catch (\Exception $e) {
            Log::error("Error getting aggregated VPS stats: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get aggregated stats'
            ], 500);
        }
    }
}
