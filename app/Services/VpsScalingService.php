<?php

namespace App\Services;

use App\Models\VpsServer;
use App\Jobs\ProvisionMultistreamVpsJob;
use Illuminate\Support\Facades\Log;

class VpsScalingService
{
    private VpsAllocationService $allocationService;

    public function __construct(VpsAllocationService $allocationService)
    {
        $this->allocationService = $allocationService;
    }

    /**
     * Check if we need to scale up VPS nodes
     */
    public function checkScalingNeeds(): array
    {
        $metrics = $this->getSystemMetrics();
        $recommendations = [];

        // Check if we need more VPS capacity
        if ($metrics['capacity_usage'] > 80) {
            $recommendations[] = [
                'action' => 'scale_up',
                'reason' => 'High capacity usage: ' . $metrics['capacity_usage'] . '%',
                'priority' => 'high'
            ];
        }

        // Check if we have too many idle VPS
        if ($metrics['idle_vps_count'] > 2) {
            $recommendations[] = [
                'action' => 'scale_down',
                'reason' => 'Too many idle VPS: ' . $metrics['idle_vps_count'],
                'priority' => 'low'
            ];
        }

        // Check for failed VPS that need replacement
        if ($metrics['failed_vps_count'] > 0) {
            $recommendations[] = [
                'action' => 'replace_failed',
                'reason' => 'Failed VPS need replacement: ' . $metrics['failed_vps_count'],
                'priority' => 'high'
            ];
        }

        return [
            'metrics' => $metrics,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Get system metrics for scaling decisions
     */
    public function getSystemMetrics(): array
    {
        $allVps = VpsServer::all();
        $activeVps = $allVps->where('status', 'ACTIVE');
        $multistreamVps = $activeVps->filter(function ($vps) {
            return $this->allocationService->hasMultistreamCapability($vps);
        });

        $totalCapacity = $multistreamVps->sum('max_concurrent_streams');
        $usedCapacity = $multistreamVps->sum('current_streams');
        $capacityUsage = $totalCapacity > 0 ? ($usedCapacity / $totalCapacity) * 100 : 0;

        $idleVps = $multistreamVps->filter(function ($vps) {
            return $vps->current_streams == 0;
        });

        $failedVps = $allVps->where('status', 'FAILED');

        return [
            'total_vps' => $allVps->count(),
            'active_vps' => $activeVps->count(),
            'multistream_vps' => $multistreamVps->count(),
            'total_capacity' => $totalCapacity,
            'used_capacity' => $usedCapacity,
            'available_capacity' => $totalCapacity - $usedCapacity,
            'capacity_usage' => round($capacityUsage, 1),
            'idle_vps_count' => $idleVps->count(),
            'failed_vps_count' => $failedVps->count(),
            'average_load' => $multistreamVps->count() > 0 ? 
                round($multistreamVps->avg(function ($vps) {
                    return $vps->max_concurrent_streams > 0 ? 
                        ($vps->current_streams / $vps->max_concurrent_streams) * 100 : 0;
                }), 1) : 0
        ];
    }

    /**
     * Auto-scale VPS based on current load
     */
    public function autoScale(): array
    {
        $analysis = $this->checkScalingNeeds();
        $actions = [];

        foreach ($analysis['recommendations'] as $recommendation) {
            switch ($recommendation['action']) {
                case 'scale_up':
                    if ($recommendation['priority'] === 'high') {
                        $result = $this->scaleUp();
                        $actions[] = $result;
                    }
                    break;

                case 'replace_failed':
                    $result = $this->replaceFailedVps();
                    $actions[] = $result;
                    break;

                case 'scale_down':
                    if ($recommendation['priority'] === 'low') {
                        // Only scale down during low usage periods
                        $result = $this->scaleDown();
                        $actions[] = $result;
                    }
                    break;
            }
        }

        return [
            'analysis' => $analysis,
            'actions_taken' => $actions
        ];
    }

    /**
     * Scale up by adding new VPS
     */
    public function scaleUp(): array
    {
        try {
            // Create new VPS configuration
            $vpsConfig = $this->generateVpsConfig();
            
            $vps = VpsServer::create($vpsConfig);

            // Dispatch provision job
            ProvisionMultistreamVpsJob::dispatch($vps);

            Log::info("🚀 Scaling up: Created new VPS", [
                'vps_id' => $vps->id,
                'name' => $vps->name
            ]);

            return [
                'action' => 'scale_up',
                'status' => 'success',
                'vps_id' => $vps->id,
                'message' => "New VPS {$vps->name} created and provisioning started"
            ];

        } catch (\Exception $e) {
            Log::error("❌ Failed to scale up", ['error' => $e->getMessage()]);

            return [
                'action' => 'scale_up',
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Scale down by removing idle VPS
     */
    public function scaleDown(): array
    {
        try {
            // Find idle VPS (no active streams)
            $idleVps = VpsServer::where('status', 'ACTIVE')
                ->where('current_streams', 0)
                ->orderBy('last_seen_at', 'asc')
                ->first();

            if (!$idleVps) {
                return [
                    'action' => 'scale_down',
                    'status' => 'skipped',
                    'message' => 'No idle VPS found for scaling down'
                ];
            }

            // Mark VPS for decommission
            $idleVps->update([
                'status' => 'DECOMMISSIONING',
                'status_message' => 'Scaling down due to low usage'
            ]);

            Log::info("📉 Scaling down: Decommissioning VPS", [
                'vps_id' => $idleVps->id,
                'name' => $idleVps->name
            ]);

            return [
                'action' => 'scale_down',
                'status' => 'success',
                'vps_id' => $idleVps->id,
                'message' => "VPS {$idleVps->name} marked for decommission"
            ];

        } catch (\Exception $e) {
            Log::error("❌ Failed to scale down", ['error' => $e->getMessage()]);

            return [
                'action' => 'scale_down',
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Replace failed VPS
     */
    public function replaceFailedVps(): array
    {
        try {
            $failedVps = VpsServer::where('status', 'FAILED')->first();

            if (!$failedVps) {
                return [
                    'action' => 'replace_failed',
                    'status' => 'skipped',
                    'message' => 'No failed VPS found'
                ];
            }

            // Create replacement VPS
            $replacementConfig = $this->generateVpsConfig();
            $replacementConfig['name'] = 'Replacement for ' . $failedVps->name;

            $newVps = VpsServer::create($replacementConfig);

            // Provision new VPS
            ProvisionMultistreamVpsJob::dispatch($newVps);

            // Mark old VPS as replaced
            $failedVps->update([
                'status' => 'REPLACED',
                'status_message' => "Replaced by VPS {$newVps->id}"
            ]);

            Log::info("🔄 Replacing failed VPS", [
                'failed_vps_id' => $failedVps->id,
                'new_vps_id' => $newVps->id
            ]);

            return [
                'action' => 'replace_failed',
                'status' => 'success',
                'old_vps_id' => $failedVps->id,
                'new_vps_id' => $newVps->id,
                'message' => "Failed VPS {$failedVps->name} replaced with {$newVps->name}"
            ];

        } catch (\Exception $e) {
            Log::error("❌ Failed to replace VPS", ['error' => $e->getMessage()]);

            return [
                'action' => 'replace_failed',
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate VPS configuration for new instances
     */
    private function generateVpsConfig(): array
    {
        $vpsCount = VpsServer::count() + 1;

        return [
            'name' => "Multistream-VPS-{$vpsCount}",
            'provider' => 'auto-scaled',
            'ip_address' => null, // Will be assigned during provision
            'ssh_user' => 'root',
            'ssh_password' => null, // Will be generated
            'ssh_port' => 22,
            'is_active' => true,
            'description' => 'Auto-scaled multistream VPS',
            'status' => 'PENDING',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 50,
            'bandwidth_gb' => 1000,
            'max_concurrent_streams' => 8, // 2 cores * 4 streams per core
            'current_streams' => 0,
        ];
    }
}
