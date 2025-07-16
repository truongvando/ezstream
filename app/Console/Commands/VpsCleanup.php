<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VpsServer;
use App\Services\SshService;
use Illuminate\Support\Facades\Log;

class VpsCleanup extends Command
{
    protected $signature = 'vps:cleanup {--force : Force cleanup even if disk usage is low}';
    protected $description = 'Clean up VPS servers - remove old files, logs, and zombie processes';

    public function handle()
    {
        $force = $this->option('force');
        $this->info('🧹 Starting VPS cleanup...');

        $vpsServers = VpsServer::where('status', 'ACTIVE')->get();

        if ($vpsServers->isEmpty()) {
            $this->info('No active VPS servers to clean.');
            return 0;
        }

        foreach ($vpsServers as $vps) {
            $this->cleanupVps($vps, $force);
        }

        $this->info('✅ VPS cleanup completed');
        return 0;
    }

    private function cleanupVps(VpsServer $vps, bool $force): void
    {
        $this->line("Cleaning VPS: {$vps->name} ({$vps->ip_address})");

        try {
            $sshService = new SshService();
            $sshService->connect($vps);

            // Check disk usage first
            $diskUsage = $this->getDiskUsage($sshService);
            $this->line("  Disk usage: {$diskUsage}%");

            // Only cleanup if disk usage > 80% or force flag
            if ($diskUsage < 80 && !$force) {
                $this->line("  Skipping - disk usage below threshold");
                $sshService->disconnect();
                return;
            }

            $this->info("  🧹 Performing cleanup...");

            // 1. Clean old stream files (older than 24 hours)
            $this->cleanOldStreamFiles($sshService);

            // 2. Clean old logs (older than 7 days)
            $this->cleanOldLogs($sshService);

            // 3. Remove zombie processes
            $this->cleanZombieProcesses($sshService);

            // 4. Clean temp files
            $this->cleanTempFiles($sshService);

            // 5. Clean package cache
            $this->cleanPackageCache($sshService);

            $sshService->disconnect();

            // Update last cleanup time
            $vps->update(['last_cleanup_at' => now()]);

            $this->info("  ✅ Cleanup completed for {$vps->name}");

        } catch (\Exception $e) {
            Log::error("VPS cleanup failed for {$vps->id}: " . $e->getMessage());
            $this->error("  ❌ Cleanup failed: " . $e->getMessage());
        }
    }

    private function getDiskUsage(SshService $sshService): int
    {
        $output = $sshService->execute("df / | tail -1 | awk '{print $5}' | sed 's/%//'");
        return (int) trim($output);
    }

    private function cleanOldStreamFiles(SshService $sshService): void
    {
        // Remove stream directories older than 24 hours
        $sshService->execute("find /tmp/stream_* -type d -mtime +1 -exec rm -rf {} + 2>/dev/null || true");
        
        // Remove old video files in /tmp
        $sshService->execute("find /tmp -name '*.mp4' -mtime +1 -delete 2>/dev/null || true");
        $sshService->execute("find /tmp -name '*.mkv' -mtime +1 -delete 2>/dev/null || true");
        
        $this->line("    - Cleaned old stream files");
    }

    private function cleanOldLogs(SshService $sshService): void
    {
        // Clean manager logs older than 7 days
        $sshService->execute("find /opt/multistream -name '*.log' -mtime +7 -delete 2>/dev/null || true");
        
        // Clean system logs
        $sshService->execute("journalctl --vacuum-time=7d 2>/dev/null || true");
        
        // Clean nginx logs older than 30 days
        $sshService->execute("find /var/log/nginx -name '*.log.*' -mtime +30 -delete 2>/dev/null || true");
        
        $this->line("    - Cleaned old logs");
    }

    private function cleanZombieProcesses(SshService $sshService): void
    {
        // Kill orphaned FFmpeg processes (running > 24 hours without manager)
        $sshService->execute("pkill -f 'ffmpeg.*stream_' 2>/dev/null || true");
        
        // Restart manager if not running
        $managerRunning = $sshService->execute("pgrep -f 'manager.py' | wc -l");
        if ((int) trim($managerRunning) === 0) {
            $sshService->execute("cd /opt/multistream && nohup python3 manager.py > manager.log 2>&1 &");
            $this->line("    - Restarted manager.py");
        }
        
        $this->line("    - Cleaned zombie processes");
    }

    private function cleanTempFiles(SshService $sshService): void
    {
        // Clean /tmp files older than 3 days
        $sshService->execute("find /tmp -type f -mtime +3 -delete 2>/dev/null || true");
        
        // Clean /var/tmp
        $sshService->execute("find /var/tmp -type f -mtime +7 -delete 2>/dev/null || true");
        
        $this->line("    - Cleaned temp files");
    }

    private function cleanPackageCache(SshService $sshService): void
    {
        // Clean apt cache
        $sshService->execute("apt-get clean 2>/dev/null || true");
        
        // Clean pip cache
        $sshService->execute("pip3 cache purge 2>/dev/null || true");
        
        $this->line("    - Cleaned package cache");
    }
}
