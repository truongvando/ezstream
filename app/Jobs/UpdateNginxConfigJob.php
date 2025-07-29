<?php

namespace App\Jobs;

use App\Models\VpsServer;
use App\Services\SshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateNginxConfigJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;

    public VpsServer $vps;

    public function __construct(VpsServer $vps)
    {
        $this->vps = $vps;
    }

    public function handle(SshService $sshService): void
    {
        Log::info("🔧 [UpdateNginxConfig] Starting nginx config update for VPS #{$this->vps->id}");

        try {
            // Connect to VPS
            if (!$sshService->connect($this->vps)) {
                throw new \Exception('Cannot connect to VPS via SSH');
            }

            // Check if buflen already exists
            $checkResult = $sshService->execute('grep -n "buflen" /etc/nginx/nginx.conf || echo "NOT_FOUND"');
            
            if (strpos($checkResult, 'NOT_FOUND') !== false) {
                Log::info("🔧 [VPS #{$this->vps->id}] Adding buflen to nginx config");
                
                // Backup current config
                $sshService->execute('cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup-' . date('Y-m-d-H-i-s'));
                
                // Add buflen after chunk_size
                $addBufferResult = $sshService->execute("sed -i '/chunk_size 4096;/a\\        buflen 120s;             # 2 minute buffer for maximum stability' /etc/nginx/nginx.conf");
                
                // Test nginx config
                $testResult = $sshService->execute('nginx -t 2>&1');
                
                if (strpos($testResult, 'syntax is ok') !== false && strpos($testResult, 'test is successful') !== false) {
                    // Reload nginx
                    $reloadResult = $sshService->execute('nginx -s reload 2>&1');
                    
                    Log::info("✅ [VPS #{$this->vps->id}] Nginx config updated successfully with buflen");
                } else {
                    // Restore backup if test failed
                    $sshService->execute('cp /etc/nginx/nginx.conf.backup-' . date('Y-m-d-H-i-s') . ' /etc/nginx/nginx.conf');
                    throw new \Exception("Nginx config test failed: {$testResult}");
                }
            } else {
                Log::info("✅ [VPS #{$this->vps->id}] Nginx config already has buflen directive");
            }

        } catch (\Exception $e) {
            Log::error("❌ [UpdateNginxConfig] Failed for VPS #{$this->vps->id}: {$e->getMessage()}");
            throw $e;
        } finally {
            $sshService->disconnect();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [UpdateNginxConfig] Job failed permanently for VPS #{$this->vps->id}", [
            'error' => $exception->getMessage(),
            'vps_name' => $this->vps->name
        ]);
    }
}
