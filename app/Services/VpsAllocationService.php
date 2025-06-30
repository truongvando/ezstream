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
} 