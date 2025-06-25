<?php

namespace App\Services;

use App\Models\VpsServer;
use App\Models\UserFile;
use App\Models\StreamConfiguration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class StreamLifecycleManager
{
    private $vpsNetworkManager;
    private $sshService;
    private $optimizedStreamingService;
    
    public function __construct(
        VpsNetworkManager $vpsNetworkManager,
        SshService $sshService,
        OptimizedStreamingService $optimizedStreamingService
    ) {
        $this->vpsNetworkManager = $vpsNetworkManager;
        $this->sshService = $sshService;
        $this->optimizedStreamingService = $optimizedStreamingService;
    }

    /**
     * Bắt đầu stream - Toàn bộ quy trình từ tìm VPS đến khởi động
     */
    public function startStream($userId, $userFileId, $streamConfig)
    {
        try {
            Log::info("🚀 Bắt đầu stream cho user {$userId}, file {$userFileId}");
            
            // 1. Phân phối stream thông minh
            $distributionResult = $this->vpsNetworkManager->distributeStream($userFileId, $streamConfig);
            
            if (!$distributionResult['success']) {
                return ['success' => false, 'error' => 'Không tìm được VPS khả dụng'];
            }
            
            $plan = $distributionResult['distribution_plan'];
            $vps = VpsServer::find($plan['primary_vps']['vps_id']);
            
            // 2. Tạo session stream
            $streamSession = $this->createStreamSession($userId, $userFileId, $vps->id, $plan);
            
            // 3. Thực hiện stream theo strategy
            $streamResult = $this->executeStreamStart($streamSession, $plan);
            
            if ($streamResult['success']) {
                // 4. Cập nhật trạng thái
                $this->updateStreamSession($streamSession->id, [
                    'status' => 'active',
                    'stream_pid' => $streamResult['stream_pid'],
                    'ffmpeg_command' => $streamResult['ffmpeg_command'],
                    'local_file_path' => $streamResult['local_file_path'] ?? null,
                    'streaming_method' => $plan['strategy']
                ]);
                
                Log::info("✅ Stream khởi động thành công: session {$streamSession->id}");
                
                return [
                    'success' => true,
                    'stream_session_id' => $streamSession->id,
                    'vps_used' => $vps->name,
                    'streaming_method' => $plan['strategy'],
                    'distribution_plan' => $plan,
                    'stream_result' => $streamResult
                ];
            } else {
                // Rollback nếu thất bại
                $this->cleanupFailedStream($streamSession);
                return ['success' => false, 'error' => $streamResult['error']];
            }
            
        } catch (\Exception $e) {
            Log::error("❌ Lỗi khởi động stream: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Dừng stream - XÓA FILE NGAY LẬP TỨC
     */
    public function stopStream($streamSessionId, $reason = 'user_stopped')
    {
        try {
            Log::info("🛑 Dừng stream session: {$streamSessionId}");
            
            // 1. Lấy thông tin session
            $streamSession = DB::table('stream_sessions')->where('id', $streamSessionId)->first();
            
            if (!$streamSession) {
                return ['success' => false, 'error' => 'Stream session không tồn tại'];
            }
            
            $vps = VpsServer::find($streamSession->vps_server_id);
            
            // 2. Dừng process FFmpeg
            $stopResult = $this->stopStreamProcess($vps, $streamSession);
            
            // 3. XÓA FILE NGAY LẬP TỨC (nếu đã tải về VPS)
            $cleanupResult = $this->immediateFileCleanup($vps, $streamSession);
            
            // 4. Cập nhật trạng thái session
            $this->updateStreamSession($streamSessionId, [
                'status' => 'stopped',
                'stopped_at' => now(),
                'stop_reason' => $reason,
                'cleanup_result' => json_encode($cleanupResult)
            ]);
            
            // 5. Cập nhật database UserFile
            $this->updateUserFileAfterStream($streamSession);
            
            Log::info("✅ Dừng stream thành công và đã dọn dẹp: session {$streamSessionId}");
            
            return [
                'success' => true,
                'stream_session_id' => $streamSessionId,
                'stop_result' => $stopResult,
                'cleanup_result' => $cleanupResult,
                'message' => 'Stream đã dừng và file đã được dọn dẹp'
            ];
            
        } catch (\Exception $e) {
            Log::error("❌ Lỗi dừng stream: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Tạo session stream mới
     */
    private function createStreamSession($userId, $userFileId, $vpsId, $plan)
    {
        $sessionData = [
            'user_id' => $userId,
            'user_file_id' => $userFileId,
            'vps_server_id' => $vpsId,
            'status' => 'initializing',
            'streaming_strategy' => $plan['strategy'],
            'estimated_setup_time' => $plan['estimated_setup_time'],
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        $sessionId = DB::table('stream_sessions')->insertGetId($sessionData);
        
        return (object) array_merge($sessionData, ['id' => $sessionId]);
    }

    /**
     * Thực hiện khởi động stream
     */
    private function executeStreamStart($streamSession, $plan)
    {
        $vps = VpsServer::find($streamSession->vps_server_id);
        $userFile = UserFile::find($streamSession->user_file_id);
        
        if ($plan['strategy'] === 'download_streaming') {
            return $this->executeDownloadStreaming($vps, $userFile, $plan);
        } else {
            return $this->executeUrlStreaming($vps, $userFile, $plan);
        }
    }

    /**
     * Thực hiện download + streaming
     */
    private function executeDownloadStreaming($vps, $userFile, $plan)
    {
        try {
            // 1. Tải file về VPS
            $downloadResult = $this->downloadFileToVps($vps, $userFile);
            
            if (!$downloadResult['success']) {
                // Fallback sang URL streaming
                Log::warning("Download thất bại, chuyển sang URL streaming");
                return $this->executeUrlStreaming($vps, $userFile, $plan);
            }
            
            // 2. Khởi động stream từ file local
            $localPath = $downloadResult['local_path'];
            $streamCommand = $this->generateLocalStreamCommand($localPath, $userFile->stream_key);
            
            // 3. Chạy command trên VPS
            $streamResult = $this->sshService->executeCommand($vps, $streamCommand, true); // background
            
            if ($streamResult['success']) {
                return [
                    'success' => true,
                    'method' => 'download_streaming',
                    'local_file_path' => $localPath,
                    'ffmpeg_command' => $streamCommand,
                    'stream_pid' => $this->extractPidFromCommand($streamResult),
                    'download_result' => $downloadResult
                ];
            } else {
                // Cleanup file nếu stream thất bại
                $this->sshService->executeCommand($vps, "rm -f '{$localPath}'");
                return ['success' => false, 'error' => 'Không thể khởi động stream'];
            }
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Thực hiện URL streaming
     */
    private function executeUrlStreaming($vps, $userFile, $plan)
    {
        try {
            // 1. Lấy URL tối ưu
            $urlResult = $this->optimizedStreamingService->getOptimizedStreamingUrl($userFile->google_drive_file_id);
            
            if (!$urlResult['success']) {
                return ['success' => false, 'error' => 'Không thể lấy streaming URL'];
            }
            
            // 2. Tạo command FFmpeg
            $streamCommand = $this->optimizedStreamingService->generateOptimizedFFmpegCommand(
                $urlResult['url'],
                $userFile->stream_key
            );
            
            // 3. Chạy trên VPS
            $streamResult = $this->sshService->executeCommand($vps, $streamCommand, true);
            
            if ($streamResult['success']) {
                return [
                    'success' => true,
                    'method' => 'url_streaming',
                    'streaming_url' => $urlResult['url'],
                    'ffmpeg_command' => $streamCommand,
                    'stream_pid' => $this->extractPidFromCommand($streamResult)
                ];
            } else {
                return ['success' => false, 'error' => 'Không thể khởi động URL stream'];
            }
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Dừng process streaming
     */
    private function stopStreamProcess($vps, $streamSession)
    {
        try {
            $commands = [];
            
            // Dừng theo PID nếu có
            if ($streamSession->stream_pid) {
                $commands[] = "kill -TERM {$streamSession->stream_pid} 2>/dev/null || true";
                $commands[] = "sleep 2 && kill -KILL {$streamSession->stream_pid} 2>/dev/null || true";
            }
            
            // Dừng tất cả FFmpeg process liên quan đến file này
            $fileName = basename($streamSession->local_file_path ?? '');
            if ($fileName) {
                $commands[] = "pkill -f 'ffmpeg.*{$fileName}' 2>/dev/null || true";
            }
            
            // Dừng theo stream key
            $commands[] = "pkill -f 'ffmpeg.*{$streamSession->user_id}' 2>/dev/null || true";
            
            $results = [];
            foreach ($commands as $command) {
                $result = $this->sshService->executeCommand($vps, $command);
                $results[] = $result;
            }
            
            return [
                'success' => true,
                'commands_executed' => $commands,
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Dọn dẹp file NGAY LẬP TỨC sau khi dừng stream
     */
    private function immediateFileCleanup($vps, $streamSession)
    {
        try {
            $cleanupResults = [];
            
            // Chỉ dọn dẹp nếu đã tải file về VPS
            if ($streamSession->streaming_method === 'download_streaming' && $streamSession->local_file_path) {
                $filePath = $streamSession->local_file_path;
                
                // Kiểm tra file có tồn tại không
                $checkCommand = "test -f '{$filePath}' && echo 'exists' || echo 'not_found'";
                $checkResult = $this->sshService->executeCommand($vps, $checkCommand);
                
                if (trim($checkResult['output']) === 'exists') {
                    // Lấy kích thước file trước khi xóa
                    $sizeCommand = "stat -c '%s' '{$filePath}' 2>/dev/null || echo '0'";
                    $sizeResult = $this->sshService->executeCommand($vps, $sizeCommand);
                    $fileSize = intval(trim($sizeResult['output']));
                    
                    // Xóa file
                    $deleteCommand = "rm -f '{$filePath}'";
                    $deleteResult = $this->sshService->executeCommand($vps, $deleteCommand);
                    
                    if ($deleteResult['success']) {
                        $cleanupResults[] = [
                            'file_path' => $filePath,
                            'file_size_gb' => round($fileSize / 1024 / 1024 / 1024, 2),
                            'deleted' => true,
                            'reason' => 'Stream stopped - immediate cleanup'
                        ];
                        
                        Log::info("🗑️ Đã xóa file ngay sau khi dừng stream: {$filePath}");
                    } else {
                        $cleanupResults[] = [
                            'file_path' => $filePath,
                            'deleted' => false,
                            'error' => 'Không thể xóa file'
                        ];
                    }
                } else {
                    $cleanupResults[] = [
                        'file_path' => $filePath,
                        'deleted' => false,
                        'reason' => 'File không tồn tại'
                    ];
                }
            }
            
            // Dọn dẹp thư mục temp liên quan
            $tempCleanupCommands = [
                "rm -f /tmp/streaming_files/*{$streamSession->user_id}* 2>/dev/null || true",
                "rm -f /tmp/ffmpeg_temp/*{$streamSession->user_id}* 2>/dev/null || true"
            ];
            
            foreach ($tempCleanupCommands as $command) {
                $this->sshService->executeCommand($vps, $command);
            }
            
            return [
                'success' => true,
                'files_cleaned' => $cleanupResults,
                'total_space_freed_gb' => array_sum(array_column($cleanupResults, 'file_size_gb')),
                'cleanup_time' => now()
            ];
            
        } catch (\Exception $e) {
            Log::error("Lỗi dọn dẹp ngay lập tức: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cập nhật session stream
     */
    private function updateStreamSession($sessionId, $data)
    {
        $data['updated_at'] = now();
        DB::table('stream_sessions')->where('id', $sessionId)->update($data);
    }

    /**
     * Cập nhật UserFile sau khi stream
     */
    private function updateUserFileAfterStream($streamSession)
    {
        UserFile::where('id', $streamSession->user_file_id)->update([
            'local_path' => null,
            'is_downloaded_to_vps' => false,
            'last_streamed_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Dọn dẹp khi stream thất bại
     */
    private function cleanupFailedStream($streamSession)
    {
        try {
            // Cập nhật trạng thái
            $this->updateStreamSession($streamSession->id, [
                'status' => 'failed',
                'stopped_at' => now()
            ]);
            
            // Dọn dẹp file nếu có
            if ($streamSession->local_file_path) {
                $vps = VpsServer::find($streamSession->vps_server_id);
                $this->sshService->executeCommand($vps, "rm -f '{$streamSession->local_file_path}'");
            }
            
        } catch (\Exception $e) {
            Log::error("Lỗi dọn dẹp stream thất bại: " . $e->getMessage());
        }
    }

    /**
     * Lấy danh sách stream đang hoạt động
     */
    public function getActiveStreams($userId = null)
    {
        $query = DB::table('stream_sessions')
            ->join('users', 'stream_sessions.user_id', '=', 'users.id')
            ->join('user_files', 'stream_sessions.user_file_id', '=', 'user_files.id')
            ->join('vps_servers', 'stream_sessions.vps_server_id', '=', 'vps_servers.id')
            ->where('stream_sessions.status', 'active')
            ->select([
                'stream_sessions.*',
                'users.name as user_name',
                'user_files.file_name',
                'vps_servers.name as vps_name',
                'vps_servers.ip_address'
            ]);
            
        if ($userId) {
            $query->where('stream_sessions.user_id', $userId);
        }
        
        return $query->orderBy('stream_sessions.created_at', 'desc')->get();
    }

    // Helper methods
    private function downloadFileToVps($vps, $userFile)
    {
        // Implementation tương tự như trong VpsNetworkManager
        // ...
        return ['success' => true, 'local_path' => '/tmp/streaming_files/' . $userFile->file_name];
    }

    private function generateLocalStreamCommand($localPath, $streamKey)
    {
        return sprintf(
            'nohup ffmpeg -re -i "%s" -c:v libx264 -preset veryfast -tune zerolatency ' .
            '-b:v 2500k -maxrate 2500k -bufsize 5000k -r 30 -s 1280x720 ' .
            '-c:a aac -b:a 128k -ar 44100 -f flv "%s" > /dev/null 2>&1 & echo $!',
            $localPath,
            $streamKey
        );
    }

    private function extractPidFromCommand($result)
    {
        // Extract PID from command output
        return trim($result['output']) ?: null;
    }
} 