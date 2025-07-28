<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class TestProgressModal extends Command
{
    protected $signature = 'test:progress-table {vps_id=63}';
    protected $description = 'Test VPS update progress in table with simulated progress';

    public function handle(): int
    {
        $vpsId = $this->argument('vps_id');
        
        $this->info("🧪 Testing progress in table for VPS #{$vpsId}");
        $this->info("📱 Open VPS Manager page to see progress in Provision Status column");
        
        // Set VPS to UPDATING status first
        $vps = \App\Models\VpsServer::find($vpsId);
        if ($vps) {
            $vps->update(['status' => 'UPDATING']);
            $this->info("✅ VPS #{$vpsId} status set to UPDATING");
        }

        $stages = [
            ['stage' => 'starting', 'progress' => 5, 'message' => 'Bắt đầu cập nhật Redis Agent v3.0'],
            ['stage' => 'connected', 'progress' => 10, 'message' => 'Kết nối SSH thành công'],
            ['stage' => 'stopping', 'progress' => 20, 'message' => 'Dừng agent hiện tại'],
            ['stage' => 'backup', 'progress' => 30, 'message' => 'Sao lưu agent hiện tại'],
            ['stage' => 'uploading', 'progress' => 50, 'message' => 'Upload agent files v3.0'],
            ['stage' => 'systemd', 'progress' => 70, 'message' => 'Cập nhật systemd service'],
            ['stage' => 'starting', 'progress' => 80, 'message' => 'Khởi động Redis Agent v3.0'],
            ['stage' => 'verifying', 'progress' => 90, 'message' => 'Kiểm tra agent đang chạy'],
            ['stage' => 'compatibility', 'progress' => 95, 'message' => 'Kiểm tra tương thích v3.0'],
            ['stage' => 'completed', 'progress' => 100, 'message' => 'Cập nhật Redis Agent v3.0 hoàn tất'],
        ];
        
        foreach ($stages as $index => $stage) {
            $progressData = [
                'vps_id' => (int) $vpsId,
                'stage' => $stage['stage'],
                'progress_percentage' => $stage['progress'],
                'message' => $stage['message'],
                'updated_at' => now()->toISOString(),
                'completed_at' => $stage['progress'] >= 100 ? now()->toISOString() : null
            ];
            
            $key = "vps_update_progress:{$vpsId}";
            Redis::setex($key, 1800, json_encode($progressData));
            
            $this->line("📊 Stage " . ($index + 1) . "/10: {$stage['message']} ({$stage['progress']}%)");
            
            // Wait 3 seconds between stages
            if ($index < count($stages) - 1) {
                sleep(3);
            }
        }
        
        // Set VPS back to ACTIVE after completion
        if ($vps) {
            $vps->update(['status' => 'ACTIVE']);
            $this->info("✅ VPS #{$vpsId} status set back to ACTIVE");
        }

        $this->info("✅ Progress simulation completed!");
        $this->info("🔍 Progress data will expire in 30 minutes");
        
        return 0;
    }
}
