<?php

namespace App\Services;

use App\Models\VpsServer;
use App\Models\UserFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VpsCleanupService
{
    private $sshService;
    
    // Cài đặt dọn dẹp
    private $cleanupRules = [
        'auto_cleanup_after_hours' => 24,        // Tự động xóa sau 24 giờ
        'max_file_age_days' => 7,                // File tối đa 7 ngày
        'disk_usage_trigger' => 85,              // Khi disk >85% thì dọn dẹp
        'keep_popular_files' => true,            // Giữ lại file được xem nhiều
        'min_views_to_keep' => 10,               // Tối thiểu 10 lượt xem mới giữ
        'cleanup_schedule' => '0 2 * * *'       // Chạy lúc 2h sáng hàng ngày
    ];

    public function __construct(SshService $sshService)
    {
        $this->sshService = $sshService;
    }

    /**
     * Dọn dẹp tự động cho tất cả VPS
     */
    public function runAutoCleanup()
    {
        $results = [];
        $vpsServers = VpsServer::where('status', 'active')->get();
        
        Log::info("Bắt đầu dọn dẹp tự động cho " . count($vpsServers) . " VPS");
        
        foreach ($vpsServers as $vps) {
            try {
                $cleanupResult = $this->cleanupVps($vps);
                $results[] = [
                    'vps_id' => $vps->id,
                    'vps_name' => $vps->name,
                    'cleanup_result' => $cleanupResult
                ];
                
                Log::info("Dọn dẹp VPS {$vps->name} thành công: " . json_encode($cleanupResult));
                
            } catch (\Exception $e) {
                Log::error("Lỗi dọn dẹp VPS {$vps->name}: " . $e->getMessage());
                $results[] = [
                    'vps_id' => $vps->id,
                    'vps_name' => $vps->name,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'success' => true,
            'total_vps_processed' => count($vpsServers),
            'cleanup_results' => $results,
            'timestamp' => now()
        ];
    }

    /**
     * Dọn dẹp một VPS cụ thể
     */
    public function cleanupVps($vps)
    {
        // 1. Kiểm tra tình trạng disk
        $diskUsage = $this->sshService->getDiskUsage($vps);
        $diskPercent = $diskUsage['usage_percent'];
        
        // 2. Lấy danh sách file trên VPS
        $vpsFiles = $this->getVpsFileList($vps);
        
        // 3. Phân tích file cần xóa
        $filesToDelete = $this->analyzeFilesForCleanup($vpsFiles, $diskPercent);
        
        // 4. Thực hiện xóa file
        $deletionResults = $this->deleteFiles($vps, $filesToDelete);
        
        // 5. Dọn dẹp thư mục rác
        $this->cleanupTempDirectories($vps);
        
        // 6. Cập nhật database
        $this->updateDatabaseAfterCleanup($vps, $filesToDelete);
        
        return [
            'disk_usage_before' => $diskPercent,
            'files_analyzed' => count($vpsFiles),
            'files_deleted' => count($filesToDelete),
            'space_freed_gb' => $deletionResults['space_freed_gb'],
            'disk_usage_after' => $this->sshService->getDiskUsage($vps)['usage_percent'],
            'cleanup_reason' => $this->getCleanupReason($diskPercent),
            'deleted_files' => $filesToDelete
        ];
    }

    /**
     * Lấy danh sách file trên VPS
     */
    private function getVpsFileList($vps)
    {
        try {
            // Lệnh tìm tất cả file video trong thư mục streaming
            $command = 'find /tmp/streaming_files -type f -name "*.mp4" -o -name "*.avi" -o -name "*.mkv" -o -name "*.mov" -exec stat -c "%n|%s|%Y" {} \;';
            
            $result = $this->sshService->executeCommand($vps, $command);
            
            if (!$result['success']) {
                return [];
            }
            
            $files = [];
            $lines = explode("\n", trim($result['output']));
            
            foreach ($lines as $line) {
                if (empty($line)) continue;
                
                $parts = explode('|', $line);
                if (count($parts) === 3) {
                    $files[] = [
                        'path' => $parts[0],
                        'size_bytes' => intval($parts[1]),
                        'modified_time' => intval($parts[2]),
                        'age_hours' => (time() - intval($parts[2])) / 3600,
                        'size_gb' => round(intval($parts[1]) / 1024 / 1024 / 1024, 2)
                    ];
                }
            }
            
            return $files;
            
        } catch (\Exception $e) {
            Log::warning("Không thể lấy danh sách file từ VPS {$vps->name}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Phân tích file nào cần xóa
     */
    private function analyzeFilesForCleanup($files, $diskPercent)
    {
        $filesToDelete = [];
        
        foreach ($files as $file) {
            $shouldDelete = false;
            $reason = '';
            
            // Quy tắc 1: File quá cũ (>7 ngày)
            if ($file['age_hours'] > ($this->cleanupRules['max_file_age_days'] * 24)) {
                $shouldDelete = true;
                $reason = 'File quá cũ (>' . $this->cleanupRules['max_file_age_days'] . ' ngày)';
            }
            
            // Quy tắc 2: Disk đầy (>85%) và file >24 giờ
            elseif ($diskPercent > $this->cleanupRules['disk_usage_trigger'] && 
                    $file['age_hours'] > $this->cleanupRules['auto_cleanup_after_hours']) {
                $shouldDelete = true;
                $reason = 'Disk đầy và file >24 giờ';
            }
            
            // Quy tắc 3: Disk rất đầy (>95%) - xóa ngay cả file mới
            elseif ($diskPercent > 95 && $file['age_hours'] > 1) {
                $shouldDelete = true;
                $reason = 'Disk cực kỳ đầy - xóa khẩn cấp';
            }
            
            // Kiểm tra file có được xem nhiều không (nếu bật tính năng này)
            if ($shouldDelete && $this->cleanupRules['keep_popular_files']) {
                $viewCount = $this->getFileViewCount($file['path']);
                if ($viewCount >= $this->cleanupRules['min_views_to_keep']) {
                    $shouldDelete = false;
                    $reason = 'Giữ lại - file được xem nhiều (' . $viewCount . ' lượt)';
                }
            }
            
            if ($shouldDelete) {
                $filesToDelete[] = array_merge($file, ['deletion_reason' => $reason]);
            }
        }
        
        // Sắp xếp theo độ ưu tiên xóa (file cũ và lớn trước)
        usort($filesToDelete, function($a, $b) {
            // Ưu tiên file cũ hơn
            $ageDiff = $b['age_hours'] - $a['age_hours'];
            if (abs($ageDiff) > 24) { // Chênh lệch >24h
                return $ageDiff > 0 ? 1 : -1;
            }
            
            // Nếu tuổi tương đương, ưu tiên file lớn hơn
            return $b['size_bytes'] - $a['size_bytes'];
        });
        
        return $filesToDelete;
    }

    /**
     * Thực hiện xóa file
     */
    private function deleteFiles($vps, $filesToDelete)
    {
        $totalSpaceFreed = 0;
        $successCount = 0;
        $errors = [];
        
        foreach ($filesToDelete as $file) {
            try {
                $deleteCommand = 'rm -f "' . $file['path'] . '"';
                $result = $this->sshService->executeCommand($vps, $deleteCommand);
                
                if ($result['success']) {
                    $totalSpaceFreed += $file['size_bytes'];
                    $successCount++;
                    Log::info("Đã xóa file: " . $file['path'] . " (" . $file['size_gb'] . "GB)");
                } else {
                    $errors[] = 'Không thể xóa: ' . $file['path'];
                }
                
            } catch (\Exception $e) {
                $errors[] = 'Lỗi xóa ' . $file['path'] . ': ' . $e->getMessage();
            }
        }
        
        return [
            'files_deleted' => $successCount,
            'space_freed_bytes' => $totalSpaceFreed,
            'space_freed_gb' => round($totalSpaceFreed / 1024 / 1024 / 1024, 2),
            'errors' => $errors
        ];
    }

    /**
     * Dọn dẹp thư mục tạm
     */
    private function cleanupTempDirectories($vps)
    {
        $tempDirs = [
            '/tmp/streaming_files',
            '/tmp/downloads',
            '/tmp/ffmpeg_temp'
        ];
        
        foreach ($tempDirs as $dir) {
            try {
                // Xóa file rác trong thư mục
                $cleanupCommand = "find {$dir} -type f -name '*.tmp' -o -name '*.part' -o -name 'core.*' -delete 2>/dev/null || true";
                $this->sshService->executeCommand($vps, $cleanupCommand);
                
                // Xóa thư mục rỗng
                $emptyDirCommand = "find {$dir} -type d -empty -delete 2>/dev/null || true";
                $this->sshService->executeCommand($vps, $emptyDirCommand);
                
            } catch (\Exception $e) {
                Log::warning("Không thể dọn dẹp thư mục {$dir}: " . $e->getMessage());
            }
        }
    }

    /**
     * Cập nhật database sau khi dọn dẹp
     */
    private function updateDatabaseAfterCleanup($vps, $deletedFiles)
    {
        try {
            foreach ($deletedFiles as $file) {
                // Tìm và cập nhật trạng thái file trong database
                $fileName = basename($file['path']);
                
                UserFile::where('file_name', $fileName)
                    ->where('vps_server_id', $vps->id)
                    ->update([
                        'local_path' => null,
                        'is_downloaded_to_vps' => false,
                        'updated_at' => now()
                    ]);
            }
            
            // Ghi log dọn dẹp
            DB::table('vps_cleanup_logs')->insert([
                'vps_server_id' => $vps->id,
                'files_deleted' => count($deletedFiles),
                'space_freed_gb' => array_sum(array_column($deletedFiles, 'size_gb')),
                'cleanup_reason' => $this->getCleanupReason(85), // placeholder
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
        } catch (\Exception $e) {
            Log::error("Lỗi cập nhật database sau dọn dẹp: " . $e->getMessage());
        }
    }

    /**
     * Lấy số lượt xem file (từ database hoặc log)
     */
    private function getFileViewCount($filePath)
    {
        try {
            $fileName = basename($filePath);
            
            // Đếm từ bảng stream logs hoặc view logs
            $count = DB::table('stream_logs')
                ->where('file_name', $fileName)
                ->count();
                
            return $count;
            
        } catch (\Exception $e) {
            return 0; // Mặc định 0 nếu không đếm được
        }
    }

    /**
     * Lấy lý do dọn dẹp
     */
    private function getCleanupReason($diskPercent)
    {
        if ($diskPercent > 95) {
            return 'Khẩn cấp - Disk >95%';
        } elseif ($diskPercent > 85) {
            return 'Tự động - Disk >85%';
        } else {
            return 'Định kỳ - Dọn dẹp file cũ';
        }
    }

    /**
     * Dọn dẹp thủ công một file cụ thể
     */
    public function manualCleanupFile($vpsId, $filePath)
    {
        try {
            $vps = VpsServer::findOrFail($vpsId);
            
            // Kiểm tra file có tồn tại không
            $checkCommand = 'test -f "' . $filePath . '" && echo "exists" || echo "not_found"';
            $checkResult = $this->sshService->executeCommand($vps, $checkCommand);
            
            if (trim($checkResult['output']) === 'not_found') {
                return [
                    'success' => false,
                    'message' => 'File không tồn tại trên VPS'
                ];
            }
            
            // Lấy thông tin file trước khi xóa
            $statCommand = 'stat -c "%s" "' . $filePath . '"';
            $statResult = $this->sshService->executeCommand($vps, $statCommand);
            $fileSize = intval(trim($statResult['output']));
            
            // Xóa file
            $deleteCommand = 'rm -f "' . $filePath . '"';
            $deleteResult = $this->sshService->executeCommand($vps, $deleteCommand);
            
            if ($deleteResult['success']) {
                // Cập nhật database
                $fileName = basename($filePath);
                UserFile::where('file_name', $fileName)
                    ->where('vps_server_id', $vps->id)
                    ->update([
                        'local_path' => null,
                        'is_downloaded_to_vps' => false,
                        'updated_at' => now()
                    ]);
                
                return [
                    'success' => true,
                    'message' => 'Đã xóa file thành công',
                    'file_path' => $filePath,
                    'file_size_gb' => round($fileSize / 1024 / 1024 / 1024, 2),
                    'vps_name' => $vps->name
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Không thể xóa file: ' . $deleteResult['error']
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lấy thống kê dọn dẹp
     */
    public function getCleanupStats($days = 30)
    {
        try {
            $stats = DB::table('vps_cleanup_logs')
                ->where('created_at', '>=', now()->subDays($days))
                ->selectRaw('
                    COUNT(*) as total_cleanups,
                    SUM(files_deleted) as total_files_deleted,
                    SUM(space_freed_gb) as total_space_freed_gb,
                    AVG(files_deleted) as avg_files_per_cleanup,
                    AVG(space_freed_gb) as avg_space_per_cleanup
                ')
                ->first();
                
            $recentCleanups = DB::table('vps_cleanup_logs')
                ->join('vps_servers', 'vps_cleanup_logs.vps_server_id', '=', 'vps_servers.id')
                ->where('vps_cleanup_logs.created_at', '>=', now()->subDays(7))
                ->select([
                    'vps_servers.name as vps_name',
                    'vps_cleanup_logs.files_deleted',
                    'vps_cleanup_logs.space_freed_gb',
                    'vps_cleanup_logs.cleanup_reason',
                    'vps_cleanup_logs.created_at'
                ])
                ->orderBy('vps_cleanup_logs.created_at', 'desc')
                ->limit(10)
                ->get();
                
            return [
                'period_days' => $days,
                'statistics' => $stats,
                'recent_cleanups' => $recentCleanups,
                'recommendations' => $this->generateCleanupRecommendations($stats)
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => 'Không thể lấy thống kê: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Tạo khuyến nghị dọn dẹp
     */
    private function generateCleanupRecommendations($stats)
    {
        $recommendations = [];
        
        if ($stats->avg_space_per_cleanup > 10) {
            $recommendations[] = '⚠️ Dung lượng dọn dẹp cao - nên giảm thời gian lưu file';
        }
        
        if ($stats->avg_files_per_cleanup > 50) {
            $recommendations[] = '📁 Nhiều file bị xóa - cân nhắc tăng dung lượng VPS';
        }
        
        if ($stats->total_cleanups > 20) {
            $recommendations[] = '🔄 Dọn dẹp quá thường xuyên - tối ưu lại quy tắc';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = '✅ Hệ thống dọn dẹp hoạt động tốt';
        }
        
        return $recommendations;
    }
} 