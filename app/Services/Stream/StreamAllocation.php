<?php

namespace App\Services\Stream;

use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Stream Allocation Service v2.0
 * Logic được đơn giản hóa, ưu tiên tài nguyên thực tế từ Redis.
 */
class StreamAllocation
{
    // Ngưỡng tài nguyên để loại bỏ một VPS
    private const CPU_THRESHOLD = 80.0;
    private const RAM_THRESHOLD = 85.0;

    public function findOptimalVps(StreamConfiguration $stream): ?VpsServer
    {
        Log::info("Finding optimal VPS for stream #{$stream->id} using real-time resource logic.");

        // 1. Lấy tất cả VPS đang hoạt động
        $activeVpsCollection = VpsServer::where('status', 'ACTIVE')->get();

        if ($activeVpsCollection->isEmpty()) {
            Log::warning("No active VPS servers found in the system.");
            return null;
        }

        // 2. Lấy thông số real-time và lọc ra các VPS "khỏe mạnh"
        $healthyVps = $activeVpsCollection->map(function ($vps) {
            $stats = $this->getVpsStatsFromRedis($vps->id);

            // VPS được coi là khỏe mạnh nếu có stats và tài nguyên dưới ngưỡng
            $isHealthy = $stats &&
                         $stats['cpu_usage'] < self::CPU_THRESHOLD &&
                         $stats['ram_usage'] < self::RAM_THRESHOLD;

            if ($isHealthy) {
                // Thêm thông số RAM để sắp xếp
                $vps->current_ram_usage = $stats['ram_usage'];
                return $vps;
            }

            return null;
        })->filter(); // Loại bỏ các VPS không khỏe mạnh (null)

        if ($healthyVps->isEmpty()) {
            Log::warning("No healthy VPS available for stream #{$stream->id}. All servers are overloaded or offline.", [
                'total_active_vps' => $activeVpsCollection->count(),
                'vps_details' => $activeVpsCollection->map(function($vps) {
                    $stats = $this->getVpsStatsFromRedis($vps->id);
                    return [
                        'id' => $vps->id,
                        'name' => $vps->name,
                        'has_stats' => !is_null($stats),
                        'cpu_usage' => $stats['cpu_usage'] ?? 'N/A',
                        'ram_usage' => $stats['ram_usage'] ?? 'N/A',
                    ];
                })->toArray()
            ]);
            return null;
        }

        // 3. Sắp xếp các VPS khỏe mạnh theo mức sử dụng RAM tăng dần và chọn cái tốt nhất
        $bestVps = $healthyVps->sortBy('current_ram_usage')->first();

        Log::info("Selected optimal VPS for stream #{$stream->id}", [
            'vps_id' => $bestVps->id,
            'vps_name' => $bestVps->name,
            'current_ram_usage' => $bestVps->current_ram_usage,
        ]);

        return $bestVps;
    }
    
    /**
     * Lấy thông số VPS mới nhất trực tiếp từ Redis cache.
     * Dữ liệu này được `agent.py` gửi về và được `UpdateVpsStatsJob` lưu trữ.
     */
    private function getVpsStatsFromRedis(int $vpsId): ?array
    {
        try {
            $statsJson = Redis::hget('vps_live_stats', $vpsId);

            if ($statsJson) {
                $statsData = json_decode($statsJson, true);
                return [
                    'cpu_usage' => $statsData['cpu_usage'] ?? 999,
                    'ram_usage' => $statsData['ram_usage'] ?? 999,
                ];
            }
        } catch (\Exception $e) {
            Log::error("Failed to get live stats for VPS #{$vpsId} from Redis.", ['error' => $e->getMessage()]);
        }
        
        // Trả về null nếu không có dữ liệu để VPS này bị loại
        return null;
    }
}
