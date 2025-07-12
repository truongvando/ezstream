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
use Illuminate\Support\Str;

class ProvisionMultistreamVpsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 600; // 10 minutes for multistream setup

    public VpsServer $vps;

    public function __construct(VpsServer $vps)
    {
        $this->vps = $vps;
        Log::info("✅ [VPS #{$this->vps->id}] Multistream provision job created");
    }

    public function handle(SshService $sshService): void
    {
        Log::info("🚀 [VPS #{$this->vps->id}] Starting multistream provision");

        try {
            // Update status to provisioning
            $this->vps->update([
                'status' => 'PROVISIONING',
                'status_message' => 'Setting up multistream capabilities...'
            ]);

            // Connect to VPS
            if (!$sshService->connect($this->vps)) {
                throw new \Exception('Failed to connect to VPS via SSH');
            }

            Log::info("✅ [VPS #{$this->vps->id}] SSH connection successful");

            // 1. Upload and run provision script
            $this->uploadProvisionScript($sshService);
            $this->runProvisionScript($sshService);

            // 2. Upload multistream manager
            $this->uploadMultistreamManager($sshService);

            // 3. Start multistream manager service
            $this->startMultistreamManager($sshService);

            // 4. Verify services are running
            $this->verifyServices($sshService);

            // 5. Update VPS status with multistream capabilities
            $maxStreams = $this->calculateMaxStreams($sshService);
            
            $this->vps->update([
                'status' => 'ACTIVE',
                'last_provisioned_at' => now(),
                'status_message' => 'Multistream ready',
                'capabilities' => json_encode(['multistream', 'nginx-rtmp', 'concurrent']),
                'max_concurrent_streams' => $maxStreams,
                'current_streams' => 0,
            ]);

            Log::info("🎉 [VPS #{$this->vps->id}] Multistream provision completed successfully", [
                'max_streams' => $maxStreams
            ]);

        } catch (\Exception $e) {
            Log::error("❌ [VPS #{$this->vps->id}] Multistream provision failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->vps->update([
                'status' => 'FAILED',
                'status_message' => 'Provision failed: ' . $e->getMessage(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $sshService->disconnect();
        }
    }

    private function uploadProvisionScript(SshService $sshService): void
    {
        Log::info("📦 [VPS #{$this->vps->id}] Uploading provision script");

        $localScript = storage_path('app/multistream/provision-vps.sh');
        $remoteScript = '/tmp/provision-multistream.sh';

        if (!file_exists($localScript)) {
            throw new \Exception('Multistream provision script not found');
        }

        if (!$sshService->uploadFile($localScript, $remoteScript)) {
            throw new \Exception('Failed to upload provision script');
        }

        // Make executable
        $sshService->execute("chmod +x {$remoteScript}");
        
        Log::info("✅ [VPS #{$this->vps->id}] Provision script uploaded");
    }

    private function runProvisionScript(SshService $sshService): void
    {
        Log::info("🔧 [VPS #{$this->vps->id}] Running provision script");

        $controllerUrl = config('app.url');
        $authToken = $this->generateAuthToken();
        
        $command = "/tmp/provision-multistream.sh {$this->vps->id} {$controllerUrl} {$authToken}";
        
        // Run with timeout
        $result = $sshService->execute($command, 300); // 5 minute timeout
        
        if (strpos($result, 'PROVISION COMPLETE') === false) {
            Log::error("❌ [VPS #{$this->vps->id}] Provision script failed", ['output' => $result]);
            throw new \Exception('Provision script execution failed');
        }

        Log::info("✅ [VPS #{$this->vps->id}] Provision script completed");
    }

    private function uploadMultistreamManager(SshService $sshService): void
    {
        Log::info("📦 [VPS #{$this->vps->id}] Uploading multistream manager");

        $localManager = storage_path('app/multistream/manager.py');
        $remoteManager = '/opt/multistream/manager.py';

        if (!file_exists($localManager)) {
            throw new \Exception('Multistream manager not found');
        }

        if (!$sshService->uploadFile($localManager, $remoteManager)) {
            throw new \Exception('Failed to upload multistream manager');
        }

        // Make executable
        $sshService->execute("chmod +x {$remoteManager}");
        
        Log::info("✅ [VPS #{$this->vps->id}] Multistream manager uploaded");
    }

    private function startMultistreamManager(SshService $sshService): void
    {
        Log::info("🚀 [VPS #{$this->vps->id}] Starting multistream manager service");

        // Enable and start the service
        $sshService->execute('systemctl daemon-reload');
        $sshService->execute('systemctl enable multistream-manager');
        $sshService->execute('systemctl start multistream-manager');

        // Wait a moment for service to start
        sleep(5);

        // Check service status
        $status = $sshService->execute('systemctl is-active multistream-manager');
        
        if (trim($status) !== 'active') {
            $serviceLog = $sshService->execute('journalctl -u multistream-manager --no-pager -l');
            Log::error("❌ [VPS #{$this->vps->id}] Multistream manager failed to start", [
                'status' => $status,
                'log' => $serviceLog
            ]);
            throw new \Exception('Multistream manager service failed to start');
        }

        Log::info("✅ [VPS #{$this->vps->id}] Multistream manager service started");
    }

    private function verifyServices(SshService $sshService): void
    {
        Log::info("🔍 [VPS #{$this->vps->id}] Verifying services");

        // Check nginx
        $nginxStatus = $sshService->execute('systemctl is-active nginx');
        if (trim($nginxStatus) !== 'active') {
            throw new \Exception('Nginx service is not running');
        }

        // Check RTMP port
        $rtmpPort = $sshService->execute('ss -tulpn | grep :1935');
        if (empty(trim($rtmpPort))) {
            throw new \Exception('RTMP port 1935 is not listening');
        }

        // Check API port
        $apiPort = $sshService->execute('ss -tulpn | grep :9999');
        if (empty(trim($apiPort))) {
            throw new \Exception('API port 9999 is not listening');
        }

        // Test health endpoint
        $healthCheck = $sshService->execute('curl -s http://localhost:8080/health');
        if (strpos($healthCheck, 'Ready') === false) {
            throw new \Exception('Health check failed');
        }

        Log::info("✅ [VPS #{$this->vps->id}] All services verified");
    }

    private function calculateMaxStreams(SshService $sshService): int
    {
        try {
            // Get CPU cores
            $cpuCores = (int) trim($sshService->execute('nproc'));
            
            // Get RAM in GB
            $ramGB = (int) trim($sshService->execute("free -g | grep Mem | awk '{print \$2}'"));
            
            // Conservative calculation
            // Each stream needs ~15% CPU and ~200MB RAM
            $maxByCpu = max(1, intval($cpuCores * 0.8 / 0.15));
            $maxByRam = max(1, intval($ramGB * 0.8 / 0.2));
            
            $maxStreams = min($maxByCpu, $maxByRam, 10); // Hard limit of 10
            
            Log::info("📊 [VPS #{$this->vps->id}] Calculated capacity", [
                'cpu_cores' => $cpuCores,
                'ram_gb' => $ramGB,
                'max_by_cpu' => $maxByCpu,
                'max_by_ram' => $maxByRam,
                'final_max' => $maxStreams
            ]);
            
            return $maxStreams;
            
        } catch (\Exception $e) {
            Log::warning("⚠️ [VPS #{$this->vps->id}] Could not calculate max streams: {$e->getMessage()}");
            return 2; // Safe default
        }
    }

    private function generateAuthToken(): string
    {
        // Generate a secure token for VPS authentication
        return Str::random(64);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("💥 [VPS #{$this->vps->id}] Multistream provision job failed", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $this->vps->update([
            'status' => 'FAILED',
            'status_message' => 'Provision failed: ' . $exception->getMessage(),
            'error_message' => $exception->getMessage(),
        ]);
    }
}
