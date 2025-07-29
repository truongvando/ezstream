<?php
/**
 * Debug script để kiểm tra Nginx RTMP trên VPS
 * Chạy: php debug-vps-nginx.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\VpsServer;
use App\Services\SshService;

// Lấy VPS đang active
$vps = VpsServer::where('status', 'active')->first();

if (!$vps) {
    echo "❌ Không tìm thấy VPS active\n";
    exit(1);
}

echo "🔍 Debugging VPS #{$vps->id} ({$vps->name})\n";
echo "📍 IP: {$vps->ip_address}\n";
echo "=" . str_repeat("=", 50) . "\n\n";

try {
    $ssh = new SshService();
    $ssh->connect($vps);
    
    // 1. Kiểm tra Nginx status
    echo "1. 🔍 Checking Nginx status...\n";
    $nginxStatus = $ssh->execute('systemctl status nginx --no-pager -l');
    echo "   Status: " . (strpos($nginxStatus, 'active (running)') !== false ? "✅ Running" : "❌ Not running") . "\n";
    if (strpos($nginxStatus, 'failed') !== false) {
        echo "   ❌ Nginx has failed:\n";
        echo "   " . str_replace("\n", "\n   ", trim($nginxStatus)) . "\n";
    }
    echo "\n";
    
    // 2. Kiểm tra port 1935
    echo "2. 🔍 Checking RTMP port 1935...\n";
    $portCheck = $ssh->execute('ss -tulpn | grep :1935');
    if (empty(trim($portCheck))) {
        echo "   ❌ Port 1935 not listening\n";
        
        // Kiểm tra nginx processes
        $nginxProc = $ssh->execute('ps aux | grep nginx | grep -v grep');
        echo "   📊 Nginx processes:\n";
        echo "   " . str_replace("\n", "\n   ", trim($nginxProc)) . "\n";
    } else {
        echo "   ✅ Port 1935 is listening\n";
        echo "   " . str_replace("\n", "\n   ", trim($portCheck)) . "\n";
    }
    echo "\n";
    
    // 3. Kiểm tra nginx config
    echo "3. 🔍 Testing Nginx config...\n";
    $configTest = $ssh->execute('nginx -t 2>&1');
    if (strpos($configTest, 'syntax is ok') !== false && strpos($configTest, 'test is successful') !== false) {
        echo "   ✅ Nginx config is valid\n";
    } else {
        echo "   ❌ Nginx config has errors:\n";
        echo "   " . str_replace("\n", "\n   ", trim($configTest)) . "\n";
    }
    echo "\n";
    
    // 4. Kiểm tra RTMP module
    echo "4. 🔍 Checking RTMP module...\n";
    $rtmpModule = $ssh->execute('nginx -V 2>&1 | grep -o rtmp');
    if (trim($rtmpModule) === 'rtmp') {
        echo "   ✅ RTMP module is loaded\n";
    } else {
        echo "   ❌ RTMP module not found\n";
        echo "   💡 Need to install: apt install libnginx-mod-rtmp\n";
    }
    echo "\n";
    
    // 5. Kiểm tra thư mục rtmp-apps
    echo "5. 🔍 Checking rtmp-apps directory...\n";
    $rtmpAppsDir = $ssh->execute('ls -la /etc/nginx/rtmp-apps/ 2>/dev/null || echo "DIRECTORY_NOT_FOUND"');
    if (strpos($rtmpAppsDir, 'DIRECTORY_NOT_FOUND') !== false) {
        echo "   ❌ Directory /etc/nginx/rtmp-apps/ not found\n";
        echo "   🔧 Creating directory...\n";
        $ssh->execute('mkdir -p /etc/nginx/rtmp-apps');
        echo "   ✅ Directory created\n";
    } else {
        echo "   ✅ Directory exists\n";
        echo "   📁 Contents:\n";
        echo "   " . str_replace("\n", "\n   ", trim($rtmpAppsDir)) . "\n";
    }
    echo "\n";
    
    // 6. Kiểm tra nginx main config có include rtmp-apps không
    echo "6. 🔍 Checking nginx main config includes...\n";
    $includeCheck = $ssh->execute('grep -n "include.*rtmp-apps" /etc/nginx/nginx.conf || echo "INCLUDE_NOT_FOUND"');
    if (strpos($includeCheck, 'INCLUDE_NOT_FOUND') !== false) {
        echo "   ❌ Main config missing rtmp-apps include\n";
        echo "   💡 Need to add: include /etc/nginx/rtmp-apps/*.conf;\n";
    } else {
        echo "   ✅ Main config includes rtmp-apps\n";
        echo "   " . str_replace("\n", "\n   ", trim($includeCheck)) . "\n";
    }
    echo "\n";
    
    // 7. Kiểm tra nginx error logs
    echo "7. 🔍 Checking recent nginx errors...\n";
    $errorLogs = $ssh->execute('tail -20 /var/log/nginx/error.log 2>/dev/null || echo "NO_ERROR_LOG"');
    if (strpos($errorLogs, 'NO_ERROR_LOG') !== false) {
        echo "   ⚠️ No error log found\n";
    } else {
        echo "   📜 Recent errors:\n";
        echo "   " . str_replace("\n", "\n   ", trim($errorLogs)) . "\n";
    }
    echo "\n";
    
    // 8. Test tạo config thử
    echo "8. 🔍 Testing config creation...\n";
    $testConfig = <<<EOF
application stream_test {
    live on;
    record off;
    allow play all;
    push rtmp://a.rtmp.youtube.com/live2/test-key;
}
EOF;
    
    $ssh->execute("echo '$testConfig' > /etc/nginx/rtmp-apps/stream_test.conf");
    $configTestAfter = $ssh->execute('nginx -t 2>&1');
    
    if (strpos($configTestAfter, 'syntax is ok') !== false) {
        echo "   ✅ Test config creation successful\n";
        $ssh->execute('rm -f /etc/nginx/rtmp-apps/stream_test.conf');
    } else {
        echo "   ❌ Test config creation failed:\n";
        echo "   " . str_replace("\n", "\n   ", trim($configTestAfter)) . "\n";
    }
    echo "\n";
    
    // 9. Restart nginx nếu cần
    echo "9. 🔧 Restarting Nginx...\n";
    $restartResult = $ssh->execute('systemctl restart nginx 2>&1');
    sleep(2);
    $statusAfterRestart = $ssh->execute('systemctl is-active nginx');
    
    if (trim($statusAfterRestart) === 'active') {
        echo "   ✅ Nginx restarted successfully\n";
        
        // Kiểm tra lại port 1935
        $portCheckAfter = $ssh->execute('ss -tulpn | grep :1935');
        if (!empty(trim($portCheckAfter))) {
            echo "   ✅ Port 1935 is now listening\n";
        } else {
            echo "   ❌ Port 1935 still not listening after restart\n";
        }
    } else {
        echo "   ❌ Nginx failed to restart\n";
        echo "   " . str_replace("\n", "\n   ", trim($restartResult)) . "\n";
    }
    
    echo "\n" . "=" . str_repeat("=", 50) . "\n";
    echo "🎯 Summary:\n";
    echo "- VPS: {$vps->name} ({$vps->ip_address})\n";
    echo "- Nginx: " . (trim($statusAfterRestart) === 'active' ? "✅ Running" : "❌ Failed") . "\n";
    echo "- RTMP Port: " . (!empty(trim($ssh->execute('ss -tulpn | grep :1935'))) ? "✅ Listening" : "❌ Not listening") . "\n";
    echo "- Config: " . (strpos($ssh->execute('nginx -t 2>&1'), 'syntax is ok') !== false ? "✅ Valid" : "❌ Invalid") . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
