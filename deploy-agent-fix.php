<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\SshService;

echo "🚀 Deploying Agent Fix to VPS #24\n";
echo "=================================\n\n";

$vpsId = 24;
$vpsIp = '103.90.227.24';

try {
    // 1. Create SSH connection
    echo "1. Connecting to VPS #{$vpsId} ({$vpsIp})...\n";
    $sshService = new SshService($vpsIp, 'root');
    
    // 2. Upload fixed agent.py
    echo "2. Uploading fixed agent.py...\n";
    $localAgentPath = storage_path('app/ezstream-agent/agent.py');
    $remoteAgentPath = '/opt/ezstream-agent/agent.py';
    
    if (!file_exists($localAgentPath)) {
        throw new Exception('Local agent.py not found');
    }
    
    if (!$sshService->uploadFile($localAgentPath, $remoteAgentPath)) {
        throw new Exception('Failed to upload agent.py');
    }
    
    echo "✅ Agent.py uploaded successfully\n\n";
    
    // 3. Restart agent service
    echo "3. Restarting agent service...\n";
    $sshService->execute('systemctl restart ezstream-agent');
    
    // Wait for service to start
    sleep(3);
    
    // 4. Check service status
    echo "4. Checking service status...\n";
    $status = $sshService->execute('systemctl is-active ezstream-agent');
    echo "Service status: " . trim($status) . "\n";
    
    if (trim($status) === 'active') {
        echo "✅ Agent service restarted successfully\n\n";
        
        // 5. Check recent logs
        echo "5. Recent agent logs:\n";
        $logs = $sshService->execute('journalctl -u ezstream-agent --no-pager -n 10');
        echo $logs . "\n";
        
    } else {
        echo "❌ Agent service failed to start\n";
        $logs = $sshService->execute('journalctl -u ezstream-agent --no-pager -n 20');
        echo "Error logs:\n" . $logs . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}

echo "\n🎯 Agent fix deployment completed!\n";
echo "💡 Monitor logs: tail -f /var/log/ezstream-agent.log\n";
echo "💡 Test stop command to see if error is fixed\n";
