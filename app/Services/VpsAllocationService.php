<?php

namespace App\Services;

use App\Models\User;
use App\Models\VpsServer;
use Illuminate\Support\Collection;
use App\Services\TelegramNotificationService;

class VpsAllocationService
{
    // Configurable thresholds (can be moved to config file later)
    protected const CPU_LOAD_THRESHOLD = 80.0;
    protected const RAM_USAGE_THRESHOLD = 85.0;
    protected const DISK_USAGE_THRESHOLD = 90.0;

    /**
     * Find the most suitable VPS server for a new stream.
     *
     * @return VpsServer|null
     */
    public function findOptimalVps(): ?VpsServer
    {
        $eligibleVps = $this->getEligibleVpsPool();

        if ($eligibleVps->isEmpty()) {
            $this->notifyAdminsOfResourceShortage();
            return null;
        }

        // ✅ SMART SELECTION - Dựa trên available_capacity từ stats real-time
        $bestVps = $eligibleVps->sortByDesc(function ($vps) {
            $latestStat = $vps->latestStat;
            
            if (!$latestStat) {
                return 100; // VPS mới, ưu tiên cao
            }
            
            // Ưu tiên VPS có available_capacity cao nhất
            $availableCapacity = $latestStat->available_capacity ?? 0;
            $ramUsage = $latestStat->ram_usage_percent ?? 0;
            $diskUsage = $latestStat->disk_usage_percent ?? 0;
            
            // Score calculation: available_capacity + penalties cho high usage
            $score = $availableCapacity;
            $score -= ($ramUsage > 70 ? ($ramUsage - 70) * 2 : 0); // Penalty cho RAM cao
            $score -= ($diskUsage > 70 ? ($diskUsage - 70) * 3 : 0); // Penalty cao hơn cho Disk
            
            return max(0, $score);
        })->first();

        return $bestVps;
    }

    /**
     * Sends a notification to all admins with configured Telegram details.
     */
    protected function notifyAdminsOfResourceShortage(): void
    {
        $admins = User::where('role', 'admin')->get();
        $telegramService = new TelegramNotificationService();
        $message = "⚠️ *System Alert: Resource Shortage*\n\nThe system is out of available VPS servers to allocate new streams. Please add more VPS to the resource pool.";

        foreach ($admins as $admin) {
            if ($admin->telegram_bot_token && $admin->telegram_chat_id) {
                $telegramService->sendMessage($admin->telegram_bot_token, $admin->telegram_chat_id, $message);
            }
        }
    }

    /**
     * Get a collection of VPS servers that are not overloaded.
     *
     * @return Collection
     */
    protected function getEligibleVpsPool(): Collection
    {
        // Eager load the latest stat for performance
        $allActiveVps = VpsServer::where('status', 'ACTIVE')
            ->with('latestStat')
            ->get();

        return $allActiveVps->filter(function (VpsServer $vps) {
            // If a server has no stats yet, consider it eligible
            if (!$vps->latestStat) {
                return true; 
            }

            $latestStat = $vps->latestStat;
            
            // ✅ PRIMARY CHECK - Available capacity từ VPS stats agent
            $availableCapacity = $latestStat->available_capacity ?? null;
            if ($availableCapacity !== null && $availableCapacity <= 0) {
                return false; // VPS đã full capacity
            }
            
            // ✅ SECONDARY CHECKS - Hard limits để safety
            $ramUsage = $latestStat->ram_usage_percent ?? 0;
            $diskUsage = $latestStat->disk_usage_percent ?? 0;
            
            // Hard limits cho safety
            if ($ramUsage >= 90) return false;  // RAM critical
            if ($diskUsage >= 95) return false; // Disk critical
            
            // ✅ SOFT CHECKS - Cho phép nếu có available_capacity
            if ($availableCapacity !== null && $availableCapacity > 0) {
                return true; // VPS báo có capacity → OK
            }
            
            // ✅ FALLBACK - Nếu không có available_capacity, dùng old logic
            $cpuUsage = $latestStat->cpu_usage_percent ?? 0;
            
            if ($cpuUsage >= self::CPU_LOAD_THRESHOLD) return false;
            if ($ramUsage >= self::RAM_USAGE_THRESHOLD) return false;
            if ($diskUsage >= self::DISK_USAGE_THRESHOLD) return false;

            return true;
        });
    }

    /**
     * Find the optimal VPS for multistream with concurrent capability
     */
    public function findOptimalMultistreamVps(): ?VpsServer
    {
        \Illuminate\Support\Facades\Log::info('🔍 Finding optimal multistream VPS');

        // Get VPS servers with multistream capability
        $eligibleVps = VpsServer::where('status', 'ACTIVE')
            ->whereJsonContains('capabilities', 'multistream')
            ->whereColumn('current_streams', '<', 'max_concurrent_streams')
            ->get();

        if ($eligibleVps->isEmpty()) {
            \Illuminate\Support\Facades\Log::warning('⚠️ No multistream VPS available');
            return null;
        }

        // Sort by available capacity (least loaded first)
        $bestVps = $eligibleVps->sortBy(function ($vps) {
            $currentStreams = $vps->current_streams ?? 0;
            $maxStreams = $vps->max_concurrent_streams ?? 1;

            // Calculate load percentage
            $loadPercentage = ($currentStreams / $maxStreams) * 100;

            // Get latest stats for additional scoring
            $latestStat = $vps->latestStat;
            $ramUsage = $latestStat->ram_usage_percent ?? 0;
            $cpuUsage = $latestStat->cpu_usage_percent ?? 0;

            // Score: lower is better
            $score = $loadPercentage;
            $score += ($ramUsage > 70 ? ($ramUsage - 70) * 2 : 0); // RAM penalty
            $score += ($cpuUsage > 70 ? ($cpuUsage - 70) * 1.5 : 0); // CPU penalty

            return $score;
        })->first();

        if ($bestVps) {
            \Illuminate\Support\Facades\Log::info('✅ Selected multistream VPS', [
                'vps_id' => $bestVps->id,
                'vps_ip' => $bestVps->ip_address,
                'current_streams' => $bestVps->current_streams,
                'max_streams' => $bestVps->max_concurrent_streams,
                'load_percentage' => round(($bestVps->current_streams / $bestVps->max_concurrent_streams) * 100, 1)
            ]);
        }

        return $bestVps;
    }

    /**
     * Check if VPS has multistream capability
     */
    public function hasMultistreamCapability(VpsServer $vps): bool
    {
        $capabilities = $vps->capabilities;

        if (is_string($capabilities)) {
            $capabilities = json_decode($capabilities, true);
        }

        return is_array($capabilities) && in_array('multistream', $capabilities);
    }

    /**
     * Get available capacity for a multistream VPS
     */
    public function getAvailableCapacity(VpsServer $vps): int
    {
        if (!$this->hasMultistreamCapability($vps)) {
            return 0;
        }

        $maxStreams = $vps->max_concurrent_streams ?? 0;
        $currentStreams = $vps->current_streams ?? 0;

        return max(0, $maxStreams - $currentStreams);
    }
}