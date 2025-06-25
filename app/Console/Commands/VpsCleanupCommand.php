<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VpsCleanupService;

class VpsCleanupCommand extends Command
{
    protected $signature = 'vps:cleanup 
                           {--vps-id= : ID của VPS cụ thể (nếu không có sẽ dọn dẹp tất cả)}
                           {--force : Bắt buộc dọn dẹp ngay cả khi disk chưa đầy}
                           {--dry-run : Chỉ hiển thị file sẽ bị xóa, không thực sự xóa}';
    
    protected $description = 'Dọn dẹp file cũ trên các VPS trong mạng lưới';

    public function handle()
    {
        $this->info('🧹 Bắt đầu dọn dẹp VPS...');
        
        $cleanupService = app(VpsCleanupService::class);
        
        if ($this->option('vps-id')) {
            // Dọn dẹp VPS cụ thể
            $this->cleanupSpecificVps($cleanupService, $this->option('vps-id'));
        } else {
            // Dọn dẹp tất cả VPS
            $this->cleanupAllVps($cleanupService);
        }
    }

    private function cleanupSpecificVps($cleanupService, $vpsId)
    {
        try {
            $vps = \App\Models\VpsServer::findOrFail($vpsId);
            $this->info("🎯 Dọn dẹp VPS: {$vps->name}");
            
            if ($this->option('dry-run')) {
                $this->warn('⚠️ CHẾ ĐỘ THỬ NGHIỆM - Không thực sự xóa file');
            }
            
            $result = $cleanupService->cleanupVps($vps);
            
            $this->displayCleanupResult($vps->name, $result);
            
        } catch (\Exception $e) {
            $this->error("❌ Lỗi dọn dẹp VPS {$vpsId}: " . $e->getMessage());
        }
    }

    private function cleanupAllVps($cleanupService)
    {
        $this->info('🌐 Dọn dẹp tất cả VPS trong mạng lưới...');
        
        if ($this->option('dry-run')) {
            $this->warn('⚠️ CHẾ ĐỘ THỬ NGHIỆM - Không thực sự xóa file');
        }
        
        $results = $cleanupService->runAutoCleanup();
        
        $this->info("📊 Kết quả tổng quan:");
        $this->table([
            'VPS', 'Disk Trước', 'File Xóa', 'Dung Lượng Giải Phóng', 'Disk Sau', 'Lý Do'
        ], $this->formatResultsForTable($results['cleanup_results']));
        
        $totalFiles = array_sum(array_column($results['cleanup_results'], 'files_deleted'));
        $totalSpace = array_sum(array_column($results['cleanup_results'], 'space_freed_gb'));
        
        $this->info("✅ Hoàn thành dọn dẹp {$results['total_vps_processed']} VPS");
        $this->info("📁 Tổng file đã xóa: {$totalFiles}");
        $this->info("💾 Tổng dung lượng giải phóng: {$totalSpace} GB");
    }

    private function displayCleanupResult($vpsName, $result)
    {
        $this->info("📈 Kết quả dọn dẹp VPS: {$vpsName}");
        $this->line("   Disk trước: {$result['disk_usage_before']}%");
        $this->line("   Disk sau: {$result['disk_usage_after']}%");
        $this->line("   File phân tích: {$result['files_analyzed']}");
        $this->line("   File đã xóa: {$result['files_deleted']}");
        $this->line("   Dung lượng giải phóng: {$result['space_freed_gb']} GB");
        $this->line("   Lý do: {$result['cleanup_reason']}");
        
        if (!empty($result['deleted_files'])) {
            $this->info("📋 Danh sách file đã xóa:");
            foreach ($result['deleted_files'] as $file) {
                $this->line("   - {$file['path']} ({$file['size_gb']} GB) - {$file['deletion_reason']}");
            }
        }
    }

    private function formatResultsForTable($results)
    {
        $tableData = [];
        
        foreach ($results as $result) {
            if (isset($result['error'])) {
                $tableData[] = [
                    $result['vps_name'],
                    'Lỗi',
                    'Lỗi',
                    'Lỗi',
                    'Lỗi',
                    $result['error']
                ];
            } else {
                $cleanup = $result['cleanup_result'];
                $tableData[] = [
                    $result['vps_name'],
                    $cleanup['disk_usage_before'] . '%',
                    $cleanup['files_deleted'],
                    $cleanup['space_freed_gb'] . ' GB',
                    $cleanup['disk_usage_after'] . '%',
                    $cleanup['cleanup_reason']
                ];
            }
        }
        
        return $tableData;
    }
} 