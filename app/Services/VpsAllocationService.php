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

        // Find the server with the lowest CPU load among the eligible ones.
        // If CPU loads are equal, it will pick the first one.
        $bestVps = $eligibleVps->sortBy('latestStat.cpu_load')->first();

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
            // If a server has no stats yet, consider it eligible but not optimal.
            if (!$vps->latestStat) {
                return true; 
            }

            $ramUsagePercent = ($vps->latestStat->ram_used_mb / $vps->latestStat->ram_total_mb) * 100;
            $diskUsagePercent = ($vps->latestStat->disk_used_gb / $vps->latestStat->disk_total_gb) * 100;
            
            if ($vps->latestStat->cpu_load >= self::CPU_LOAD_THRESHOLD) {
                return false;
            }

            if ($ramUsagePercent >= self::RAM_USAGE_THRESHOLD) {
                return false;
            }

            if ($diskUsagePercent >= self::DISK_USAGE_THRESHOLD) {
                return false;
            }

            return true;
        });
    }
} 