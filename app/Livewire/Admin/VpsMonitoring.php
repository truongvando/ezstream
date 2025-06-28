<?php

namespace App\Livewire\Admin;

use App\Models\VpsServer;
use App\Models\VpsStat;
use App\Services\SshService;
use Livewire\Component;
use Illuminate\Support\Facades\Cache;

class VpsMonitoring extends Component
{
    public $selectedVps = null;
    public $showRealTime = false;
    public $autoRefresh = true;
    public $refreshInterval = 10; // seconds - faster refresh for realtime monitoring

    protected $listeners = ['refreshMonitoring' => 'render'];

    public function mount()
    {
        // Auto-select first VPS if available
        $firstVps = VpsServer::where('status', 'ACTIVE')->first();
        if ($firstVps) {
            $this->selectedVps = $firstVps->id;
        }
        
        // Auto-sync stats on page load if no recent data
        $this->ensureFreshStats();
    }

    public function selectVps($vpsId)
    {
        $this->selectedVps = $vpsId;
    }

    public function toggleRealTime()
    {
        $this->showRealTime = !$this->showRealTime;
    }

    public function toggleAutoRefresh()
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    public function refreshNow()
    {
        // Force sync stats for all VPS
        $this->syncAllVpsStats();
        $this->dispatch('refreshMonitoring');
        session()->flash('message', 'VPS stats refreshed successfully!');
    }

    public function ensureFreshStats()
    {
        // Check if any VPS has stale stats (older than 10 minutes)
        $staleVps = VpsServer::where('status', 'ACTIVE')
            ->with('latestStat')
            ->get()
            ->filter(function ($vps) {
                return !$vps->latestStat || $vps->latestStat->created_at->lt(now()->subMinutes(10));
            });

        if ($staleVps->count() > 0) {
            // Dispatch background job for each VPS individually
            foreach ($staleVps as $vps) {
                dispatch(new \App\Jobs\SyncVpsStatsJob($vps));
            }
        }
    }

    private function syncAllVpsStats()
    {
        // Use webhook-based stats collection instead of SSH polling
        $vpsServers = VpsServer::where('status', 'ACTIVE')->get();

        foreach ($vpsServers as $vps) {
            // Check if VPS has stats agent running (webhook-based)
            $hasRecentWebhookData = VpsStat::where('vps_server_id', $vps->id)
                ->where('created_at', '>', now()->subMinutes(5))
                ->exists();

            if ($hasRecentWebhookData) {
                // VPS is sending webhook data - no need to SSH poll
                continue;
            }

            // Fallback to SSH polling for VPS without webhook agent
            try {
                $sshService = new SshService();
                $cpuUsage = $sshService->getCpuUsage($vps);
                $ramUsage = $sshService->getRamUsage($vps);
                $diskUsage = $sshService->getDiskUsage($vps);

                VpsStat::create([
                    'vps_server_id' => $vps->id,
                    'cpu_usage_percent' => $cpuUsage,
                    'ram_usage_percent' => $ramUsage,
                    'disk_usage_percent' => $diskUsage,
                ]);

                \Log::info("SSH fallback stats collected for VPS {$vps->id} (no webhook agent)");
            } catch (\Exception $e) {
                \Log::error("Failed to sync VPS {$vps->id} via SSH: " . $e->getMessage());
            }
        }
    }

    public function getRealTimeStats($vpsId)
    {
        if (!$this->showRealTime) {
            return null;
        }

        // Get latest webhook data first (preferred)
        $latestStat = VpsStat::where('vps_server_id', $vpsId)
            ->where('created_at', '>', now()->subMinutes(2))
            ->orderBy('created_at', 'desc')
            ->first();

        if ($latestStat) {
            return [
                'cpu' => $latestStat->cpu_usage_percent,
                'ram' => $latestStat->ram_usage_percent,
                'disk' => $latestStat->disk_usage_percent,
                'timestamp' => $latestStat->created_at,
                'source' => 'webhook',
            ];
        }

        // Fallback to SSH if no recent webhook data
        $cacheKey = "realtime_stats_vps_{$vpsId}";
        
        return Cache::remember($cacheKey, 30, function () use ($vpsId) {
            $vps = VpsServer::find($vpsId);
            if (!$vps) return null;

            try {
                $sshService = new SshService();
                return [
                    'cpu' => $sshService->getCpuUsage($vps),
                    'ram' => $sshService->getRamUsage($vps),
                    'disk' => $sshService->getDiskUsage($vps),
                    'timestamp' => now(),
                    'source' => 'ssh_fallback',
                ];
            } catch (\Exception $e) {
                return [
                    'error' => $e->getMessage(),
                    'timestamp' => now(),
                    'source' => 'error',
                ];
            }
        });
    }

    public function render()
    {
        $vpsServers = VpsServer::where('status', 'ACTIVE')
            ->with(['latestStat', 'stats' => function($query) {
                $query->orderBy('created_at', 'desc')->limit(20);
            }])
            ->get()
            ->map(function ($vps) {
                $latestStat = $vps->latestStat;
                // More lenient online check - 15 minutes instead of 5
                $isOnline = $latestStat && $latestStat->created_at->gt(now()->subMinutes(15));

                // Get real-time stats if enabled
                $realTimeStats = $this->getRealTimeStats($vps->id);

                // Check webhook agent status
                $hasRecentWebhookData = VpsStat::where('vps_server_id', $vps->id)
                    ->where('created_at', '>', now()->subMinutes(3))
                    ->exists();

                return [
                    'id' => $vps->id,
                    'name' => $vps->name,
                    'ip_address' => $vps->ip_address,
                    'status' => $isOnline ? 'online' : 'offline',
                    'webhook_status' => $hasRecentWebhookData ? 'active' : 'inactive',
                    'last_updated' => $latestStat ? $latestStat->created_at->diffForHumans() : 'N/A',
                    'cpu_usage_percent' => $isOnline ? $latestStat->cpu_usage_percent : 0,
                    'ram_usage_percent' => $isOnline ? $latestStat->ram_usage_percent : 0,
                    'disk_usage_percent' => $isOnline ? $latestStat->disk_usage_percent : 0,
                    'historical_stats' => $vps->stats->take(10)->map(function($stat) {
                        return [
                            'cpu' => $stat->cpu_usage_percent,
                            'ram' => $stat->ram_usage_percent,
                            'disk' => $stat->disk_usage_percent,
                            'time' => $stat->created_at->format('H:i'),
                        ];
                    }),
                    'realtime_stats' => $realTimeStats,
                    'data_source' => $realTimeStats['source'] ?? 'none',
                    'error' => $realTimeStats['error'] ?? null,
                ];
            });

        $selectedVpsData = null;
        if ($this->selectedVps) {
            $selectedVpsData = $vpsServers->firstWhere('id', $this->selectedVps);
        }

        return view('livewire.admin.vps-monitoring', [
            'vpsServers' => $vpsServers,
            'selectedVpsData' => $selectedVpsData,
        ])
        ->layout('layouts.sidebar')
        ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">VPS Monitoring</h1>');
    }
} 