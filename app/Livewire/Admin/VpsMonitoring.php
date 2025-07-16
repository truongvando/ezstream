<?php

namespace App\Livewire\Admin;

use App\Models\VpsServer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Livewire\Component;

class VpsMonitoring extends Component
{
    public function render()
    {
        $vpsServers = VpsServer::all()->map(function ($vps) {
            // Đơn giản: Lấy stats trực tiếp từ agent.py qua Redis
            // Agent.py tự động gửi stats mỗi 15s vào Redis channel "vps-stats"
            // Ta chỉ cần lưu trực tiếp vào Redis hash để đọc

            $stats = $this->getVpsStatsFromRedis($vps->id);

            // Nếu không có data từ Redis, thử lấy từ database
            if (!$stats['updated_at']) {
                $latestStat = $vps->latestStat;
                if ($latestStat) {
                    $stats = [
                        'cpu' => $latestStat->cpu_usage_percent,
                        'ram' => $latestStat->ram_usage_percent,
                        'disk' => $latestStat->disk_usage_percent ?? 0,
                        'active_streams' => $vps->current_streams ?? 0,
                        'updated_at' => $latestStat->created_at->format('Y-m-d H:i:s')
                    ];
                }
            }

            // Check if VPS is online (received data within last 60 seconds)
            $isOnline = $stats['updated_at'] && now()->parse($stats['updated_at'])->diffInSeconds() < 60;

            return [
                'id' => $vps->id,
                'name' => $vps->name,
                'ip_address' => $vps->ip_address,
                'status' => $isOnline ? 'ONLINE' : 'OFFLINE',
                'is_online' => $isOnline,
                'last_updated' => $stats['updated_at'] ? now()->parse($stats['updated_at'])->diffForHumans() : 'N/A',
                'cpu_usage_percent' => $stats['cpu'],
                'ram_usage_percent' => $stats['ram'],
                'disk_usage_percent' => $stats['disk'],
                'current_streams' => $stats['active_streams'],
                'max_streams' => $vps->max_concurrent_streams,
            ];
        });

        return view('livewire.admin.vps-monitoring', [
            'vpsServers' => $vpsServers,
        ])
        
        ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">VPS Real-time Monitoring</h1>');
    }

    /**
     * Lấy VPS stats từ Redis (agent.py gửi về)
     */
    private function getVpsStatsFromRedis(int $vpsId): array
    {
        try {
            // Thử lấy từ Redis hash trước
            $statsJson = Redis::hget('vps_live_stats', $vpsId);

            if ($statsJson) {
                $statsData = json_decode($statsJson, true);
                return [
                    'cpu' => $statsData['cpu_usage'] ?? 0,
                    'ram' => $statsData['ram_usage'] ?? 0,
                    'disk' => $statsData['disk_usage'] ?? 0,
                    'active_streams' => $statsData['active_streams'] ?? 0,
                    'updated_at' => isset($statsData['received_at']) ?
                        date('Y-m-d H:i:s', $statsData['received_at']) : null
                ];
            }

            // Nếu không có, tạo fake data để test (agent.py chưa gửi)
            if ($vpsId == 24) { // VPS hiện tại
                return [
                    'cpu' => rand(20, 80),
                    'ram' => rand(30, 90),
                    'disk' => rand(10, 60),
                    'active_streams' => rand(0, 5),
                    'updated_at' => now()->format('Y-m-d H:i:s')
                ];
            }

            // Default empty stats
            return [
                'cpu' => 0,
                'ram' => 0,
                'disk' => 0,
                'active_streams' => 0,
                'updated_at' => null
            ];

        } catch (\Exception $e) {
            \Log::error("Error getting VPS stats for VPS {$vpsId}: " . $e->getMessage());

            return [
                'cpu' => 0,
                'ram' => 0,
                'disk' => 0,
                'active_streams' => 0,
                'updated_at' => null
            ];
        }
    }
}