<?php

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Redis;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TEST VPS STATS WITH DISK USAGE ===\n\n";

// 1. Kiểm tra VPS stats hiện tại trong Redis
echo "1. KIỂM TRA VPS STATS HIỆN TẠI:\n";

try {
    $vpsStats = Redis::hgetall('vps_live_stats');
    
    if (empty($vpsStats)) {
        echo "   ❌ Không có VPS stats nào trong Redis\n";
        echo "   💡 Đảm bảo agent.py đang chạy và gửi stats\n\n";
    } else {
        echo "   📊 Tìm thấy stats cho " . count($vpsStats) . " VPS:\n\n";
        
        foreach ($vpsStats as $vpsId => $statsJson) {
            $stats = json_decode($statsJson, true);
            
            echo "   🖥️ VPS #{$vpsId}:\n";
            echo "     CPU: " . ($stats['cpu_usage'] ?? 'N/A') . "%\n";
            echo "     RAM: " . ($stats['ram_usage'] ?? 'N/A') . "%\n";
            
            // Kiểm tra disk usage mới
            if (isset($stats['disk_usage'])) {
                echo "     ✅ Disk: {$stats['disk_usage']}%\n";
                if (isset($stats['disk_used_gb']) && isset($stats['disk_total_gb'])) {
                    echo "     📁 Disk Space: {$stats['disk_used_gb']}GB / {$stats['disk_total_gb']}GB\n";
                }
                if (isset($stats['disk_free_gb'])) {
                    echo "     💾 Free Space: {$stats['disk_free_gb']}GB\n";
                }
            } else {
                echo "     ❌ Disk: Chưa có dữ liệu (agent.py cũ)\n";
            }
            
            echo "     🎬 Active Streams: " . ($stats['active_streams'] ?? 'N/A') . "\n";
            
            // Kiểm tra network stats mới
            if (isset($stats['network_sent_mb']) && isset($stats['network_recv_mb'])) {
                echo "     🌐 Network: ↑{$stats['network_sent_mb']}MB ↓{$stats['network_recv_mb']}MB\n";
            }
            
            // Kiểm tra thời gian cập nhật
            if (isset($stats['received_at'])) {
                $minutesAgo = (time() - $stats['received_at']) / 60;
                $status = $minutesAgo <= 2 ? '✅ FRESH' : '⚠️ STALE';
                echo "     ⏰ Last Update: {$status} (" . round($minutesAgo, 1) . " phút trước)\n";
            }
            
            echo "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ❌ Lỗi khi truy cập Redis: " . $e->getMessage() . "\n\n";
}

// 2. Kiểm tra format dữ liệu mới
echo "2. KIỂM TRA FORMAT DỮ LIỆU MỚI:\n";

$expectedFields = [
    'vps_id' => 'VPS ID',
    'cpu_usage' => 'CPU Usage (%)',
    'ram_usage' => 'RAM Usage (%)',
    'disk_usage' => 'Disk Usage (%)',
    'disk_total_gb' => 'Total Disk (GB)',
    'disk_used_gb' => 'Used Disk (GB)',
    'disk_free_gb' => 'Free Disk (GB)',
    'active_streams' => 'Active Streams',
    'network_sent_mb' => 'Network Sent (MB)',
    'network_recv_mb' => 'Network Received (MB)',
    'timestamp' => 'Timestamp',
    'received_at' => 'Received At (Laravel)'
];

echo "   📋 Expected fields trong VPS stats:\n";
foreach ($expectedFields as $field => $description) {
    echo "     - {$field}: {$description}\n";
}

echo "\n";

// 3. Tạo sample data để test
echo "3. TẠO SAMPLE DATA ĐỂ TEST:\n";

$sampleStats = [
    'vps_id' => 999,
    'cpu_usage' => 15.5,
    'ram_usage' => 45.2,
    'disk_usage' => 67.8,
    'disk_total_gb' => 50.0,
    'disk_used_gb' => 33.9,
    'disk_free_gb' => 16.1,
    'active_streams' => 2,
    'network_sent_mb' => 1024.5,
    'network_recv_mb' => 2048.7,
    'timestamp' => time()
];

try {
    // Thêm received_at
    $sampleStats['received_at'] = time();
    
    // Lưu vào Redis
    Redis::hset('vps_live_stats', 999, json_encode($sampleStats));
    
    echo "   ✅ Đã tạo sample stats cho VPS #999\n";
    echo "   📊 Sample data:\n";
    echo "     CPU: {$sampleStats['cpu_usage']}%\n";
    echo "     RAM: {$sampleStats['ram_usage']}%\n";
    echo "     Disk: {$sampleStats['disk_usage']}% ({$sampleStats['disk_used_gb']}GB / {$sampleStats['disk_total_gb']}GB)\n";
    echo "     Network: ↑{$sampleStats['network_sent_mb']}MB ↓{$sampleStats['network_recv_mb']}MB\n";
    
    // Xóa sample data sau 10 giây
    echo "   🧹 Sample data sẽ tự động xóa sau 10 giây...\n";
    
} catch (\Exception $e) {
    echo "   ❌ Lỗi tạo sample data: " . $e->getMessage() . "\n";
}

echo "\n";

// 4. Hướng dẫn cập nhật agent.py
echo "4. HƯỚNG DẪN CẬP NHẬT AGENT.PY:\n";
echo "   📝 Để agent.py gửi disk usage:\n";
echo "     1. Cập nhật file agent.py trên VPS\n";
echo "     2. Restart agent.py process\n";
echo "     3. Kiểm tra logs: tail -f /var/log/ezstream-agent.log\n";
echo "     4. Monitor Redis: redis-cli monitor | grep vps-stats\n\n";

echo "   🔄 Commands để restart agent:\n";
echo "     sudo supervisorctl restart ezstream-agent\n";
echo "     # Hoặc\n";
echo "     sudo systemctl restart ezstream-agent\n\n";

// 5. Cleanup sample data
sleep(1);
try {
    Redis::hdel('vps_live_stats', 999);
    echo "   🧹 Đã xóa sample data\n";
} catch (\Exception $e) {
    echo "   ⚠️ Không thể xóa sample data: " . $e->getMessage() . "\n";
}

echo "\n=== KẾT THÚC TEST ===\n";
