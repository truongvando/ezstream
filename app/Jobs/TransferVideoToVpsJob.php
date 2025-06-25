<?php

namespace App\Jobs;

use App\Models\UserFile;
use App\Services\SshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TransferVideoToVpsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public UserFile $userFile)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(SshService $sshService): void
    {
        $user = $this->userFile->user;
        
        // Logic to select a VPS. For now, we'll pick the first active one.
        // This can be made more sophisticated later.
        $vps = $user->vpsServers()->where('status', 'ACTIVE')->first();

        if (!$vps) {
            $this->failAndLog('No active VPS server available for this user.');
            return;
        }

        if (!$sshService->connect($vps)) {
            $this->failAndLog('Failed to connect to VPS via SSH.');
            return;
        }

        $localPath = Storage::disk($this->userFile->disk)->path($this->userFile->path);
        // Sanitize filename to be safe for shell commands
        $safeFilename = preg_replace('/[^A-Za-z0-9\._-]/', '', $this->userFile->original_name);
        $remotePath = "/home/videos/livestream/{$user->id}/{$safeFilename}";

        if ($sshService->uploadFile($localPath, $remotePath)) {
            $this->userFile->update([
                'vps_server_id' => $vps->id,
                'path' => $remotePath, // Update path to the remote path
                'disk' => 'vps', // Update disk to reflect remote storage
                'status' => 'AVAILABLE',
                'status_message' => null,
            ]);
            Log::info("File transfer successful for UserFile ID: {$this->userFile->id}");
        } else {
            $this->failAndLog('File transfer via SFTP failed.');
        }

        $sshService->disconnect();

        // Delete the temporary local file after transfer
        Storage::disk($this->userFile->disk)->delete($this->userFile->path);
    }
    
    protected function failAndLog(string $message): void
    {
        $this->userFile->update([
            'status' => 'FAILED',
            'status_message' => $message,
        ]);
        Log::error("File transfer failed for UserFile ID {$this->userFile->id}: {$message}");
    }
}
