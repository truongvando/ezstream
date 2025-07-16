<?php

namespace App\Services\Vps;

use App\Models\VpsServer;
use App\Services\SshService;
use Illuminate\Support\Facades\Log;

/**
 * VPS Connection Service
 * Chỉ chịu trách nhiệm: Quản lý SSH connections và basic commands
 */
class VpsConnection
{
    private SshService $sshService;
    
    public function __construct(SshService $sshService)
    {
        $this->sshService = $sshService;
    }
    
    /**
     * Test VPS connectivity
     */
    public function testConnection(VpsServer $vps): array
    {
        try {
            $startTime = microtime(true);
            
            if (!$this->sshService->connect($vps)) {
                return [
                    'success' => false,
                    'error' => 'Failed to establish SSH connection',
                    'latency' => null
                ];
            }
            
            // Test basic command
            $output = $this->sshService->execute('echo "test"');
            $latency = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->sshService->disconnect();
            
            if (trim($output) === 'test') {
                return [
                    'success' => true,
                    'latency' => $latency,
                    'message' => 'Connection successful'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Command execution failed',
                    'latency' => $latency
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'latency' => null
            ];
        }
    }
    
    /**
     * Execute command on VPS
     */
    public function executeCommand(VpsServer $vps, string $command): array
    {
        try {
            if (!$this->sshService->connect($vps)) {
                return [
                    'success' => false,
                    'error' => 'Failed to connect to VPS',
                    'output' => null,
                    'exit_code' => null
                ];
            }
            
            $output = $this->sshService->execute($command);
            $exitCode = $this->sshService->getExitCode();
            
            $this->sshService->disconnect();
            
            return [
                'success' => $exitCode === 0,
                'output' => $output,
                'exit_code' => $exitCode,
                'error' => $exitCode !== 0 ? "Command failed with exit code {$exitCode}" : null
            ];
            
        } catch (\Exception $e) {
            Log::error("VPS command execution failed", [
                'vps_id' => $vps->id,
                'command' => $command,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'output' => null,
                'exit_code' => null
            ];
        }
    }
    
    /**
     * Upload file to VPS
     */
    public function uploadFile(VpsServer $vps, string $localPath, string $remotePath): bool
    {
        try {
            if (!$this->sshService->connect($vps)) {
                return false;
            }
            
            $success = $this->sshService->uploadFile($localPath, $remotePath);
            $this->sshService->disconnect();
            
            return $success;
            
        } catch (\Exception $e) {
            Log::error("VPS file upload failed", [
                'vps_id' => $vps->id,
                'local_path' => $localPath,
                'remote_path' => $remotePath,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Download file from VPS
     */
    public function downloadFile(VpsServer $vps, string $remotePath, string $localPath): bool
    {
        try {
            if (!$this->sshService->connect($vps)) {
                return false;
            }
            
            $success = $this->sshService->downloadFile($remotePath, $localPath);
            $this->sshService->disconnect();
            
            return $success;
            
        } catch (\Exception $e) {
            Log::error("VPS file download failed", [
                'vps_id' => $vps->id,
                'remote_path' => $remotePath,
                'local_path' => $localPath,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Read file content from VPS
     */
    public function readFile(VpsServer $vps, string $remotePath): ?string
    {
        try {
            if (!$this->sshService->connect($vps)) {
                return null;
            }
            
            $content = $this->sshService->readFile($remotePath);
            $this->sshService->disconnect();
            
            return $content;
            
        } catch (\Exception $e) {
            Log::error("VPS file read failed", [
                'vps_id' => $vps->id,
                'remote_path' => $remotePath,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Check if VPS manager is running
     */
    public function isManagerRunning(VpsServer $vps): bool
    {
        $result = $this->executeCommand($vps, 'pgrep -f "manager.py" | wc -l');
        
        if (!$result['success']) {
            return false;
        }
        
        return intval(trim($result['output'])) > 0;
    }
    
    /**
     * Get VPS system info
     */
    public function getSystemInfo(VpsServer $vps): array
    {
        $commands = [
            'uptime' => 'uptime',
            'disk_usage' => 'df -h /',
            'memory_info' => 'free -h',
            'cpu_info' => 'nproc',
            'load_average' => 'cat /proc/loadavg'
        ];
        
        $info = [];
        
        foreach ($commands as $key => $command) {
            $result = $this->executeCommand($vps, $command);
            $info[$key] = $result['success'] ? trim($result['output']) : 'N/A';
        }
        
        return $info;
    }
}
