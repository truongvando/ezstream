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
use Throwable;

class ProvisionVpsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * The VPS server instance.
     * @var VpsServer
     */
    public VpsServer $vps;

    /**
     * Create a new job instance.
     */
    public function __construct(VpsServer $vps)
    {
        $this->vps = $vps;
        Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] Job Constructed.");
    }

    /**
     * Execute the job.
     */
    public function handle(SshService $sshService): void
    {
        Log::channel('provisioning')->info("▶️ [VPS #{$this->vps->id}] Job Handling Started.");
        
        $this->vps->update(['status' => 'PROVISIONING', 'status_message' => 'Connecting to VPS...']);

        // This will now throw an exception on failure, which is caught by the 'failed' method.
        $sshService->connect($this->vps);
        
        Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] SSH Connection Successful.");

        // --- Define provisioning commands ---
        $commands = [
            'update_system' => [
                'message' => 'Updating system packages...',
                'command' => 'DEBIAN_FRONTEND=noninteractive apt-get update -y'
            ],
            'install_ffmpeg' => [
                'message' => 'Installing FFmpeg...',
                'command' => 'DEBIAN_FRONTEND=noninteractive apt-get install ffmpeg -y'
            ],
            'create_dir' => [
                'message' => 'Creating livestream directory...',
                'command' => 'mkdir -p /home/videos/livestream'
            ],
            'set_permissions' => [
                'message' => 'Setting directory permissions...',
                'command' => 'chmod -R 755 /home/videos'
            ]
        ];

        // --- Execute each command ---
        foreach ($commands as $step => $details) {
            $this->vps->update(['status_message' => $details['message']]);
            Log::channel('provisioning')->info("🔄 [VPS #{$this->vps->id}] Executing [{$step}]: {$details['command']}");

            $output = $sshService->execute($details['command']);
            $exitCode = $sshService->getExitCode();

            if ($exitCode !== 0) {
                $errorMessage = "Step [{$step}] failed. Exit Code: {$exitCode}.";
                Log::channel('provisioning')->error("❌ [VPS #{$this->vps->id}] {$errorMessage}", ['output' => $output]);
                $sshService->disconnect();
                return;
            }
            Log::channel('provisioning')->info("✅ [VPS #{$this->vps->id}] Step [{$step}] successful.", ['output' => substr($output, 0, 1000)]);
        }

        // --- Finalize ---
        $this->vps->update(['status' => 'ACTIVE', 'status_message' => 'Provisioned successfully!']);
        Log::channel('provisioning')->info("🎉 [VPS #{$this->vps->id}] Provisioned successfully!");

        $sshService->disconnect();
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::channel('provisioning')->error("--- 💣 [VPS #{$this->vps->id}] JOB FAILED ---");
        Log::channel('provisioning')->error("Error: " . $exception->getMessage());
        
        $this->vps->refresh();
        $this->vps->update([
            'status' => 'PROVISION_FAILED',
            'status_message' => 'Job failed: ' . $exception->getMessage(),
        ]);
    }
} 