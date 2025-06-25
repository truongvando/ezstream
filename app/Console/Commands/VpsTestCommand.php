<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VpsServer;
use App\Services\SshService;

class VpsTestCommand extends Command
{
    protected $signature = 'vps:test {vps_id? : ID của VPS muốn test}';
    protected $description = 'Test SSH connection và kiểm tra trạng thái VPS';

    public function handle()
    {
        $vpsId = $this->argument('vps_id');
        
        if ($vpsId) {
            $vps = VpsServer::find($vpsId);
            if (!$vps) {
                $this->error("❌ Không tìm thấy VPS với ID: {$vpsId}");
                return 1;
            }
            $this->testSingleVps($vps);
        } else {
            $this->listAndTestAllVps();
        }

        return 0;
    }

    private function listAndTestAllVps()
    {
        $this->info("📋 Danh sách tất cả VPS:");
        $vpsList = VpsServer::all();

        if ($vpsList->isEmpty()) {
            $this->warn("⚠️ Chưa có VPS nào trong hệ thống");
            return;
        }

        foreach ($vpsList as $vps) {
            $statusIcon = match($vps->status) {
                'ACTIVE' => '✅',
                'PROVISIONING' => '🔄',
                'PROVISION_FAILED' => '❌',
                'PENDING' => '⏳',
                default => '❓'
            };

            $this->line("{$statusIcon} [{$vps->id}] {$vps->name} ({$vps->ip_address}) - {$vps->status}");
        }

        $vpsId = $this->ask("🔍 Nhập ID VPS muốn test chi tiết (hoặc Enter để thoát)");
        
        if ($vpsId) {
            $vps = VpsServer::find($vpsId);
            if ($vps) {
                $this->testSingleVps($vps);
            } else {
                $this->error("❌ Không tìm thấy VPS với ID: {$vpsId}");
            }
        }
    }

    private function testSingleVps(VpsServer $vps)
    {
        $this->info("🔍 Testing VPS: {$vps->name}");
        $this->line("📍 IP: {$vps->ip_address}:{$vps->ssh_port}");
        $this->line("👤 User: {$vps->ssh_user}");
        $this->line("📊 Status: {$vps->status}");
        if ($vps->status_message) {
            $this->line("💬 Message: {$vps->status_message}");
        }
        $this->newLine();

        $sshService = app(SshService::class);

        try {
            $this->info("🔄 Attempting SSH connection...");
            
            $connected = $sshService->connect($vps);

            if ($connected) {
                $this->info("✅ SSH Connection: SUCCESS!");
                $this->newLine();

                // Test basic commands
                $this->testCommand($sshService, 'whoami', 'Current user');
                $this->testCommand($sshService, 'pwd', 'Current directory');
                $this->testCommand($sshService, 'uname -a', 'System info');
                $this->testCommand($sshService, 'uptime', 'System uptime');
                
                // Test disk space
                $this->testCommand($sshService, 'df -h /', 'Disk usage');
                
                // Test FFmpeg
                $ffmpegResult = $sshService->execute('which ffmpeg');
                if ($ffmpegResult && trim($ffmpegResult)) {
                    $this->info("🎬 FFmpeg: INSTALLED at " . trim($ffmpegResult));
                    $this->testCommand($sshService, 'ffmpeg -version | head -1', 'FFmpeg version');
                } else {
                    $this->warn("⚠️ FFmpeg: NOT INSTALLED");
                }

                // Test directories
                $this->testCommand($sshService, 'ls -la /home/videos/livestream', 'Livestream directory');

                $sshService->disconnect();
                $this->newLine();
                $this->info("✅ SSH Test completed successfully!");

                // Suggest provision retry if failed
                if ($vps->status === 'PROVISION_FAILED') {
                    if ($this->confirm("🔄 VPS connection works! Do you want to retry provisioning?")) {
                        $this->retryProvisioning($vps);
                    }
                }

            } else {
                $this->error("❌ SSH Connection: FAILED");
                $this->newLine();
                $this->warn("💡 Common causes:");
                $this->line("   • Incorrect IP address or port");
                $this->line("   • Wrong username/password");
                $this->line("   • SSH service not running on VPS");
                $this->line("   • Firewall blocking connection");
                $this->line("   • VPS not properly set up");
                $this->newLine();
                
                $this->suggestSolutions($vps);
            }

        } catch (\Exception $e) {
            $this->error("❌ Exception occurred: " . $e->getMessage());
            $this->line("🔍 Error class: " . get_class($e));
        }
    }

    private function testCommand(SshService $sshService, string $command, string $description)
    {
        $result = $sshService->execute($command);
        $output = $result ? trim($result) : 'No output';
        $this->line("🧪 {$description}: {$output}");
    }

    private function retryProvisioning(VpsServer $vps)
    {
        $this->info("🔄 Retrying provisioning for {$vps->name}...");
        
        $vps->update([
            'status' => 'PENDING',
            'status_message' => null
        ]);

        \App\Jobs\ProvisionVpsJob::dispatch($vps);
        
        $this->info("✅ Provision job dispatched! Run 'php artisan queue:work' to process.");
    }

    private function suggestSolutions(VpsServer $vps)
    {
        $this->warn("🔧 Troubleshooting suggestions:");
        $this->line("1. Verify VPS credentials:");
        $this->line("   php artisan tinker");
        $this->line("   >> \$vps = App\\Models\\VpsServer::find({$vps->id});");
        $this->line("   >> dump(\$vps->ssh_password); // Check if password is correct");
        $this->newLine();
        
        $this->line("2. Test from command line:");
        $this->line("   ssh {$vps->ssh_user}@{$vps->ip_address} -p {$vps->ssh_port}");
        $this->newLine();
        
        $this->line("3. Check VPS provider dashboard");
        $this->line("4. Verify firewall settings");
        $this->line("5. Make sure SSH service is enabled");
    }
} 