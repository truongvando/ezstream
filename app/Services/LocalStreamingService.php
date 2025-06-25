<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class LocalStreamingService
{
    private array $activeProcesses = [];
    
    /**
     * Test FFmpeg streaming locally
     */
    public function testLocalStream(array $options = []): array
    {
        $inputFile = $options['input_file'] ?? null;
        $outputUrl = $options['output_url'] ?? 'rtmp://localhost/live/test';
        $preset = $options['preset'] ?? 'direct';
        $duration = $options['duration'] ?? 0; // 0 = no limit
        
        if (!$inputFile || !file_exists($inputFile)) {
            return [
                'success' => false,
                'error' => 'Input file not found'
            ];
        }
        
        // Build FFmpeg command
        $command = $this->buildFFmpegCommand($inputFile, $outputUrl, $preset, $duration);
        
        Log::info('Testing local FFmpeg stream', [
            'command' => $command,
            'input' => $inputFile,
            'output' => $outputUrl
        ]);
        
        try {
            // Create process
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(null); // No timeout
            $process->setIdleTimeout(null);
            
            // Start process
            $process->start();
            $pid = $process->getPid();
            
            $this->activeProcesses[$pid] = $process;
            
            // Collect output for a few seconds
            sleep(3);
            
            $output = '';
            $error = '';
            
            // Get current output
            $output .= $process->getIncrementalOutput();
            $error .= $process->getIncrementalErrorOutput();
            
            return [
                'success' => true,
                'pid' => $pid,
                'running' => $process->isRunning(),
                'command' => $command,
                'output' => $output,
                'error' => $error,
                'message' => 'Stream started successfully. Use stopLocalStream(' . $pid . ') to stop.'
            ];
            
        } catch (ProcessFailedException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'output' => $e->getProcess()->getOutput(),
                'error_output' => $e->getProcess()->getErrorOutput()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test streaming from Google Drive URL
     */
    public function testGoogleDriveStream(string $fileId, array $options = []): array
    {
        $googleDriveService = app(GoogleDriveService::class);
        $optimizedStreamingService = app(OptimizedStreamingService::class);
        
        // Get optimized streaming URL
        $streamData = $optimizedStreamingService->getOptimizedStreamingUrl($fileId);
        
        if (!$streamData['success']) {
            return [
                'success' => false,
                'error' => 'Cannot get streaming URL: ' . ($streamData['error'] ?? 'Unknown error')
            ];
        }
        
        $streamUrl = $streamData['url'];
        $outputUrl = $options['output_url'] ?? 'rtmp://localhost/live/test';
        $preset = $options['preset'] ?? 'optimized';
        $duration = $options['duration'] ?? 0; // 0 = no limit
        
        // Build FFmpeg command for URL streaming
        $command = $this->buildFFmpegCommandForUrl($streamUrl, $outputUrl, $preset, $duration);
        
        Log::info('Testing Google Drive stream locally', [
            'file_id' => $fileId,
            'stream_url' => substr($streamUrl, 0, 100) . '...',
            'command' => $command
        ]);
        
        try {
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(null);
            $process->start();
            
            $pid = $process->getPid();
            $this->activeProcesses[$pid] = $process;
            
            // Wait a bit and collect output
            sleep(5);
            
            return [
                'success' => true,
                'pid' => $pid,
                'running' => $process->isRunning(),
                'stream_url' => $streamUrl,
                'stream_method' => $streamData['method'],
                'command' => $command,
                'output' => $process->getIncrementalOutput(),
                'error' => $process->getIncrementalErrorOutput()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Build FFmpeg command for local file
     */
    private function buildFFmpegCommand(string $inputFile, string $outputUrl, string $preset, int $duration): string
    {
        $baseCommand = 'ffmpeg -re';
        
        // Add duration limit for testing
        if ($duration > 0) {
            $baseCommand .= " -t {$duration}";
        }
        
        // Input file
        $baseCommand .= ' -i ' . escapeshellarg($inputFile);
        
        // Preset options
        $presetOptions = $this->getPresetOptions($preset);
        
        // Output
        $baseCommand .= " {$presetOptions} -f flv " . escapeshellarg($outputUrl);
        
        // Add progress stats
        $baseCommand .= " -progress - -stats";
        
        return $baseCommand;
    }
    
    /**
     * Build FFmpeg command for URL streaming
     */
    private function buildFFmpegCommandForUrl(string $inputUrl, string $outputUrl, string $preset, int $duration): string
    {
        $baseCommand = 'ffmpeg -re';
        
        // Add headers for Google Drive
        $baseCommand .= ' -headers "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"';
        $baseCommand .= ' -headers "Referer: https://drive.google.com/"';
        
        // Reconnect options for network streams
        $baseCommand .= ' -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5';
        
        // Duration limit
        if ($duration > 0) {
            $baseCommand .= " -t {$duration}";
        }
        
        // Input URL
        $baseCommand .= ' -i ' . escapeshellarg($inputUrl);
        
        // Preset options
        $presetOptions = $this->getPresetOptions($preset);
        
        // Output
        $baseCommand .= " {$presetOptions} -f flv " . escapeshellarg($outputUrl);
        
        // Progress stats
        $baseCommand .= " -progress - -stats";
        
        return $baseCommand;
    }
    
    /**
     * Get preset options
     */
    private function getPresetOptions(string $preset): string
    {
        switch ($preset) {
            case 'direct':
                return '-c:v copy -c:a copy';
                
            case 'optimized':
                return '-c:v libx264 -preset veryfast -crf 23 -maxrate 3000k -bufsize 6000k ' .
                       '-c:a aac -b:a 128k -ar 44100';
                       
            case 'high_quality':
                return '-c:v libx264 -preset medium -crf 20 -maxrate 5000k -bufsize 10000k ' .
                       '-c:a aac -b:a 192k -ar 48000';
                       
            case 'low_latency':
                return '-c:v libx264 -preset ultrafast -tune zerolatency -crf 25 ' .
                       '-maxrate 2000k -bufsize 4000k -c:a aac -b:a 96k';
                       
            case 'youtube':
                return '-c:v libx264 -preset veryfast -crf 20 -maxrate 4500k -bufsize 9000k ' .
                       '-pix_fmt yuv420p -g 60 -c:a aac -b:a 128k -ar 44100';
                       
            case 'facebook':
                return '-c:v libx264 -preset veryfast -crf 23 -maxrate 4000k -bufsize 8000k ' .
                       '-pix_fmt yuv420p -r 30 -g 60 -c:a aac -b:a 128k -ar 44100';
                       
            default:
                return '-c:v libx264 -preset veryfast -crf 23 -c:a aac -b:a 128k';
        }
    }
    
    /**
     * Stop a local stream
     */
    public function stopLocalStream(int $pid): array
    {
        if (!isset($this->activeProcesses[$pid])) {
            return [
                'success' => false,
                'error' => 'Process not found'
            ];
        }
        
        $process = $this->activeProcesses[$pid];
        
        try {
            $process->stop(3, 15); // SIGTERM = 15
            
            // Get final output
            $output = $process->getOutput();
            $error = $process->getErrorOutput();
            
            unset($this->activeProcesses[$pid]);
            
            return [
                'success' => true,
                'message' => 'Stream stopped successfully',
                'final_output' => $output,
                'final_error' => $error
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get status of a stream
     */
    public function getStreamStatus(int $pid): array
    {
        if (!isset($this->activeProcesses[$pid])) {
            return [
                'success' => false,
                'error' => 'Process not found'
            ];
        }
        
        $process = $this->activeProcesses[$pid];
        
        return [
            'success' => true,
            'pid' => $pid,
            'running' => $process->isRunning(),
            'output' => $process->getIncrementalOutput(),
            'error' => $process->getIncrementalErrorOutput()
        ];
    }
    
    /**
     * Test FFmpeg installation
     */
    public function testFFmpegInstallation(): array
    {
        try {
            $process = new Process(['ffmpeg', '-version']);
            $process->run();
            
            if (!$process->isSuccessful()) {
                return [
                    'success' => false,
                    'error' => 'FFmpeg not found or not working properly',
                    'output' => $process->getErrorOutput()
                ];
            }
            
            // Parse version info
            $output = $process->getOutput();
            preg_match('/ffmpeg version ([^\s]+)/', $output, $matches);
            $version = $matches[1] ?? 'Unknown';
            
            // Check for important codecs
            $hasH264 = strpos($output, 'libx264') !== false;
            $hasAAC = strpos($output, 'aac') !== false;
            
            return [
                'success' => true,
                'version' => $version,
                'output' => $output,
                'codecs' => [
                    'h264' => $hasH264,
                    'aac' => $hasAAC
                ],
                'message' => "FFmpeg {$version} is installed and working"
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to check FFmpeg: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate test video if needed
     */
    public function generateTestVideo(string $outputPath, int $duration = 10): array
    {
        $command = sprintf(
            'ffmpeg -f lavfi -i testsrc=duration=%d:size=1280x720:rate=30 ' .
            '-f lavfi -i sine=frequency=1000:duration=%d ' .
            '-c:v libx264 -preset ultrafast -crf 20 ' .
            '-c:a aac -b:a 128k ' .
            '-y %s',
            $duration,
            $duration,
            escapeshellarg($outputPath)
        );
        
        try {
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(60);
            $process->run();
            
            if (!$process->isSuccessful()) {
                return [
                    'success' => false,
                    'error' => 'Failed to generate test video',
                    'output' => $process->getErrorOutput()
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Test video generated successfully',
                'path' => $outputPath,
                'size' => filesize($outputPath)
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
} 