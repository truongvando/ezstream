<?php

namespace App\Services;

use App\Models\UserFile;
use App\Models\StreamConfiguration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AutoDeleteMonitoringService
{
    /**
     * Get auto-delete statistics
     */
    public function getStatistics(): array
    {
        try {
            $stats = [
                'scheduled_deletions' => $this->getScheduledDeletionsStats(),
                'completed_deletions' => $this->getCompletedDeletionsStats(),
                'storage_savings' => $this->getStorageSavingsStats(),
                'recent_activity' => $this->getRecentActivityStats(),
                'error_summary' => $this->getErrorSummaryStats()
            ];

            return [
                'success' => true,
                'data' => $stats,
                'generated_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error("❌ [AutoDeleteMonitoring] Failed to get statistics: {$e->getMessage()}");
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get scheduled deletions statistics
     */
    private function getScheduledDeletionsStats(): array
    {
        $scheduledFiles = UserFile::where('auto_delete_after_stream', true)
            ->whereNotNull('scheduled_deletion_at')
            ->where('status', '!=', 'DELETED')
            ->get();

        $overdue = $scheduledFiles->where('scheduled_deletion_at', '<=', now());
        $upcoming = $scheduledFiles->where('scheduled_deletion_at', '>', now());

        return [
            'total_scheduled' => $scheduledFiles->count(),
            'overdue' => $overdue->count(),
            'upcoming_24h' => $upcoming->where('scheduled_deletion_at', '<=', now()->addDay())->count(),
            'upcoming_7d' => $upcoming->where('scheduled_deletion_at', '<=', now()->addWeek())->count(),
            'total_size_scheduled' => $scheduledFiles->sum('size'),
            'overdue_files' => $overdue->take(10)->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filename' => $file->original_name,
                    'size' => $file->size,
                    'scheduled_at' => $file->scheduled_deletion_at,
                    'overdue_hours' => now()->diffInHours($file->scheduled_deletion_at)
                ];
            })->toArray()
        ];
    }

    /**
     * Get completed deletions statistics
     */
    private function getCompletedDeletionsStats(): array
    {
        $deletedFiles = UserFile::where('status', 'DELETED')
            ->where('auto_delete_after_stream', true)
            ->whereNotNull('deleted_at');

        $today = $deletedFiles->clone()->whereDate('deleted_at', today());
        $thisWeek = $deletedFiles->clone()->where('deleted_at', '>=', now()->startOfWeek());
        $thisMonth = $deletedFiles->clone()->where('deleted_at', '>=', now()->startOfMonth());

        return [
            'total_deleted' => $deletedFiles->count(),
            'deleted_today' => $today->count(),
            'deleted_this_week' => $thisWeek->count(),
            'deleted_this_month' => $thisMonth->count(),
            'total_size_deleted' => $deletedFiles->sum('size'),
            'size_deleted_today' => $today->sum('size'),
            'size_deleted_this_week' => $thisWeek->sum('size'),
            'size_deleted_this_month' => $thisMonth->sum('size')
        ];
    }

    /**
     * Get storage savings statistics
     */
    private function getStorageSavingsStats(): array
    {
        $totalDeleted = UserFile::where('status', 'DELETED')
            ->where('auto_delete_after_stream', true)
            ->sum('size');

        $totalScheduled = UserFile::where('auto_delete_after_stream', true)
            ->whereNotNull('scheduled_deletion_at')
            ->where('status', '!=', 'DELETED')
            ->sum('size');

        return [
            'total_space_freed' => $totalDeleted,
            'potential_space_to_free' => $totalScheduled,
            'total_potential_savings' => $totalDeleted + $totalScheduled,
            'formatted' => [
                'space_freed' => $this->formatBytes($totalDeleted),
                'potential_savings' => $this->formatBytes($totalScheduled),
                'total_savings' => $this->formatBytes($totalDeleted + $totalScheduled)
            ]
        ];
    }

    /**
     * Get recent activity statistics
     */
    private function getRecentActivityStats(): array
    {
        $recentDeletions = UserFile::where('status', 'DELETED')
            ->where('auto_delete_after_stream', true)
            ->where('deleted_at', '>=', now()->subHours(24))
            ->orderBy('deleted_at', 'desc')
            ->take(10)
            ->get();

        $recentScheduled = UserFile::where('auto_delete_after_stream', true)
            ->whereNotNull('scheduled_deletion_at')
            ->where('scheduled_deletion_at', '>=', now()->subHours(24))
            ->orderBy('scheduled_deletion_at', 'desc')
            ->take(10)
            ->get();

        return [
            'recent_deletions' => $recentDeletions->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filename' => $file->original_name,
                    'size' => $file->size,
                    'deleted_at' => $file->deleted_at,
                    'user_id' => $file->user_id
                ];
            })->toArray(),
            'recent_scheduled' => $recentScheduled->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filename' => $file->original_name,
                    'size' => $file->size,
                    'scheduled_at' => $file->scheduled_deletion_at,
                    'user_id' => $file->user_id
                ];
            })->toArray()
        ];
    }

    /**
     * Get error summary statistics
     */
    private function getErrorSummaryStats(): array
    {
        // This would require a separate error tracking table in a real implementation
        // For now, we'll return basic info
        $failedFiles = UserFile::where('auto_delete_after_stream', true)
            ->whereNotNull('scheduled_deletion_at')
            ->where('scheduled_deletion_at', '<=', now()->subHours(2))
            ->where('status', '!=', 'DELETED')
            ->get();

        return [
            'potentially_failed' => $failedFiles->count(),
            'files_stuck_over_2h' => $failedFiles->count(),
            'needs_investigation' => $failedFiles->take(5)->map(function ($file) {
                return [
                    'id' => $file->id,
                    'filename' => $file->original_name,
                    'scheduled_at' => $file->scheduled_deletion_at,
                    'hours_overdue' => now()->diffInHours($file->scheduled_deletion_at)
                ];
            })->toArray()
        ];
    }

    /**
     * Get auto-delete health status
     */
    public function getHealthStatus(): array
    {
        try {
            $overdueCount = UserFile::where('auto_delete_after_stream', true)
                ->whereNotNull('scheduled_deletion_at')
                ->where('scheduled_deletion_at', '<=', now()->subHours(1))
                ->where('status', '!=', 'DELETED')
                ->count();

            $recentActivity = UserFile::where('status', 'DELETED')
                ->where('auto_delete_after_stream', true)
                ->where('deleted_at', '>=', now()->subHours(24))
                ->count();

            $status = 'healthy';
            $issues = [];

            if ($overdueCount > 10) {
                $status = 'warning';
                $issues[] = "High number of overdue deletions: {$overdueCount}";
            }

            if ($overdueCount > 50) {
                $status = 'critical';
                $issues[] = "Critical: Too many overdue deletions: {$overdueCount}";
            }

            if ($recentActivity === 0 && $overdueCount > 0) {
                $status = 'warning';
                $issues[] = "No recent deletion activity but files are scheduled";
            }

            return [
                'status' => $status,
                'overdue_count' => $overdueCount,
                'recent_activity_24h' => $recentActivity,
                'issues' => $issues,
                'last_checked' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'last_checked' => now()->toISOString()
            ];
        }
    }

    /**
     * Cache and return dashboard data
     */
    public function getDashboardData(): array
    {
        return Cache::remember('auto_delete_dashboard', 300, function () {
            return [
                'statistics' => $this->getStatistics(),
                'health' => $this->getHealthStatus(),
                'cached_at' => now()->toISOString()
            ];
        });
    }

    /**
     * Clear dashboard cache
     */
    public function clearCache(): void
    {
        Cache::forget('auto_delete_dashboard');
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Log auto-delete operation
     */
    public function logOperation(string $operation, array $data): void
    {
        Log::info("📊 [AutoDeleteMonitoring] {$operation}", array_merge($data, [
            'timestamp' => now()->toISOString()
        ]));
    }
}
