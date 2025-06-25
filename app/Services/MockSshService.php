<?php

namespace App\Services;

use App\Models\VpsServer;
use Illuminate\Support\Facades\Log;

/**
 * Mock SSH Service for local testing without real VPS
 */
class MockSshService extends SshService
{
    private array $mockProcesses = [];
    private array $mockFiles = [];
    private array $mockStats = [
        'cpu' => 25.5,
        'memory' => 45.2,
        'disk' => 60.0,
        'network_in' => 1024,
        'network_out' => 2048
    ];

    /**
     * Mock connect - always successful
     */
    public function connect(VpsServer $vps): bool
    {
        Log::info('[MOCK SSH] Connected to VPS: ' . $vps->name);
        return true;
    }

    /**
     * Mock disconnect
     */
    public function disconnect(): void
    {
        Log::info('[MOCK SSH] Disconnected');
    }

    /**
     * Mock execute command
     */
    public function executeCommand(VpsServer $vps, string $command, bool $async = false): array
    {
        Log::info('[MOCK SSH] Executing command', [
            'vps' => $vps->name,
            'command' => $command,
            'async' => $async
        ]);

        // Parse different commands
        if (str_contains($command, 'ffmpeg')) {
            return $this->mockFfmpegCommand($command);
        }

        if (str_contains($command, 'kill')) {
            return $this->mockKillCommand($command);
        }

        if (str_contains($command, 'ps aux')) {
            return $this->mockPsCommand();
        }

        if (str_contains($command, 'df -h')) {
            return $this->mockDfCommand();
        }

        if (str_contains($command, 'free -m')) {
            return $this->mockFreeCommand();
        }

        if (str_contains($command, 'find')) {
            return $this->mockFindCommand();
        }

        if (str_contains($command, 'rm -f')) {
            return $this->mockRmCommand($command);
        }

        return [
            'success' => true,
            'output' => 'Command executed successfully',
            'error' => null
        ];
    }

    /**
     * Mock execute in background and get PID
     */
    public function executeInBackgroundAndGetPid(string $command): ?int
    {
        $pid = rand(1000, 9999);
        
        Log::info('[MOCK SSH] Starting background process', [
            'command' => substr($command, 0, 100) . '...',
            'pid' => $pid
        ]);

        // Store mock process
        $this->mockProcesses[$pid] = [
            'command' => $command,
            'started_at' => now(),
            'status' => 'running'
        ];

        return $pid;
    }

    /**
     * Mock FFmpeg command
     */
    private function mockFfmpegCommand(string $command): array
    {
        // Extract stream info from command
        if (preg_match('/rtmp:\/\/[^\s]+/', $command, $matches)) {
            $rtmpUrl = $matches[0];
            Log::info('[MOCK SSH] FFmpeg streaming to: ' . $rtmpUrl);
        }

        return [
            'success' => true,
            'output' => "FFmpeg mock output\nStream started successfully",
            'error' => null
        ];
    }

    /**
     * Mock kill command
     */
    private function mockKillCommand(string $command): array
    {
        if (preg_match('/kill -9 (\d+)/', $command, $matches)) {
            $pid = $matches[1];
            
            if (isset($this->mockProcesses[$pid])) {
                $this->mockProcesses[$pid]['status'] = 'killed';
                Log::info('[MOCK SSH] Process killed', ['pid' => $pid]);
                
                return [
                    'success' => true,
                    'output' => '',
                    'error' => null
                ];
            }
        }

        return [
            'success' => false,
            'output' => '',
            'error' => 'Process not found'
        ];
    }

    /**
     * Mock ps command
     */
    private function mockPsCommand(): array
    {
        $output = "USER       PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND\n";
        
        foreach ($this->mockProcesses as $pid => $process) {
            if ($process['status'] === 'running') {
                $output .= sprintf(
                    "root     %5d  5.2  2.1 123456 12345 ?        Sl   10:00   0:05 %s\n",
                    $pid,
                    substr($process['command'], 0, 50)
                );
            }
        }

        return [
            'success' => true,
            'output' => $output,
            'error' => null
        ];
    }

    /**
     * Mock df command
     */
    private function mockDfCommand(): array
    {
        $diskUsage = $this->mockStats['disk'];
        $output = "Filesystem      Size  Used Avail Use% Mounted on\n";
        $output .= sprintf("/dev/sda1        50G   %dG   %dG  %d%% /\n", 
            (int)($diskUsage * 0.5), 
            (int)((100 - $diskUsage) * 0.5),
            (int)$diskUsage
        );

        return [
            'success' => true,
            'output' => $output,
            'error' => null
        ];
    }

    /**
     * Mock free command
     */
    private function mockFreeCommand(): array
    {
        $memUsage = $this->mockStats['memory'];
        $totalMem = 8192;
        $usedMem = (int)($totalMem * $memUsage / 100);
        $freeMem = $totalMem - $usedMem;

        $output = "              total        used        free      shared  buff/cache   available\n";
        $output .= sprintf("Mem:        %6d      %6d      %6d         100        1000      %6d\n",
            $totalMem, $usedMem, $freeMem, $freeMem - 500
        );

        return [
            'success' => true,
            'output' => $output,
            'error' => null
        ];
    }

    /**
     * Mock find command for cleanup
     */
    private function mockFindCommand(): array
    {
        $output = "";
        
        // Generate some mock files
        for ($i = 1; $i <= 5; $i++) {
            $age = rand(1, 10);
            $size = rand(100, 5000) * 1024 * 1024; // MB to bytes
            $timestamp = time() - ($age * 24 * 60 * 60);
            
            $output .= sprintf(
                "/tmp/streaming_files/video_%d.mp4|%d|%d\n",
                $i, $size, $timestamp
            );
        }

        return [
            'success' => true,
            'output' => trim($output),
            'error' => null
        ];
    }

    /**
     * Mock rm command
     */
    private function mockRmCommand(string $command): array
    {
        if (preg_match('/rm -f (.+)/', $command, $matches)) {
            $file = $matches[1];
            Log::info('[MOCK SSH] File deleted', ['file' => $file]);
        }

        return [
            'success' => true,
            'output' => '',
            'error' => null
        ];
    }

    /**
     * Get VPS stats
     */
    public function getVpsStats(VpsServer $vps): array
    {
        // Simulate some variation
        $this->mockStats['cpu'] = max(0, min(100, $this->mockStats['cpu'] + rand(-5, 5)));
        $this->mockStats['memory'] = max(0, min(100, $this->mockStats['memory'] + rand(-3, 3)));
        
        return $this->mockStats;
    }

    /**
     * Update mock stats for testing
     */
    public function setMockStats(array $stats): void
    {
        $this->mockStats = array_merge($this->mockStats, $stats);
    }

    /**
     * Get mock processes for testing
     */
    public function getMockProcesses(): array
    {
        return $this->mockProcesses;
    }
} 