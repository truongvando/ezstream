<?php

require_once __DIR__ . '/vendor/autoload.php';

// Load Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 VPS Available for Webhook Deployment\n";
echo "=======================================\n\n";

// Get all VPS
$vpsServers = \App\Models\VpsServer::all();

if ($vpsServers->count() === 0) {
    echo "❌ No VPS found in database\n";
    echo "💡 Add a VPS first via admin panel\n";
    exit(1);
}

echo "📋 Found {$vpsServers->count()} VPS server(s):\n\n";

foreach ($vpsServers as $vps) {
    echo "🖥️  VPS #{$vps->id}: {$vps->name}\n";
    echo "   IP: {$vps->ip_address}\n";
    echo "   Status: {$vps->status}\n";
    echo "   SSH: {$vps->ssh_user}@{$vps->ip_address}:{$vps->ssh_port}\n";
    
    // Check recent stats (webhook working?)
    $recentStats = \App\Models\VpsStat::where('vps_server_id', $vps->id)
        ->where('created_at', '>', now()->subMinutes(5))
        ->count();
        
    if ($recentStats > 0) {
        echo "   📊 ✅ Webhook Active ({$recentStats} recent stats)\n";
    } else {
        echo "   📊 ❌ No webhook data (need to deploy agent)\n";
    }
    
    echo "\n";
}

// Show deployment options
echo "🚀 Deployment Options:\n";
echo "1. Auto-deploy via ProvisionVpsJob (recommended)\n";
echo "2. Manual SSH deployment\n";
echo "3. Re-provision existing VPS\n\n";

echo "💡 Webhook Benefits vs SSH Polling:\n";
echo "✅ Realtime (1 minute vs 5-10 minutes)\n";
echo "✅ Efficient (1 HTTP request vs SSH connection)\n";
echo "✅ Scalable (1000 VPS = 1000 requests vs 1000 SSH)\n";
echo "✅ Event-driven (VPS can send alerts)\n";
echo "✅ Auto-recovery (backup RTMP, error notifications)\n";
echo "✅ Less server load (no SSH overhead)\n\n";

echo "📡 Current Webhook Endpoint:\n";
echo "   " . config('app.url') . "/api/vps/vps-stats\n\n";

echo "🔧 Next: Deploy agent to VPS for real webhook testing\n";

?> 