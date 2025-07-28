<?php

namespace App\Console\Commands;

use App\Jobs\UpdateAgentJob;
use App\Models\VpsServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestUpdateAgent extends Command
{
    protected $signature = 'test:update-agent {vps_id?} {--dry-run}';
    protected $description = 'Test UpdateAgentJob with agent v3.0 compatibility';

    public function handle(): int
    {
        $vpsId = $this->argument('vps_id');
        $dryRun = $this->option('dry-run');

        if (!$vpsId) {
            $vps = VpsServer::first();
            if (!$vps) {
                $this->error('❌ No VPS found in database');
                return 1;
            }
            $vpsId = $vps->id;
        } else {
            $vps = VpsServer::find($vpsId);
            if (!$vps) {
                $this->error("❌ VPS #{$vpsId} not found");
                return 1;
            }
        }

        $this->info("🧪 Testing UpdateAgentJob for VPS #{$vps->id} ({$vps->name})");

        if ($dryRun) {
            $this->warn("🔍 DRY RUN MODE - No actual update will be performed");
            
            // Check agent files exist
            $agentDir = storage_path('app/ezstream-agent');
            $requiredFiles = [
                'agent.py',
                'command_handler.py', 
                'config.py',
                'status_reporter.py',
                'stream_manager.py',
                'process_manager.py',
                'file_manager.py',
                'utils.py',
                'ezstream-agent-logrotate.conf'
            ];

            $this->info("📁 Checking agent files in: {$agentDir}");
            
            $missingFiles = [];
            foreach ($requiredFiles as $file) {
                $filePath = "{$agentDir}/{$file}";
                if (file_exists($filePath)) {
                    $this->line("  ✅ {$file}");
                } else {
                    $this->line("  ❌ {$file} - MISSING");
                    $missingFiles[] = $file;
                }
            }

            if (!empty($missingFiles)) {
                $this->error("❌ Missing required files: " . implode(', ', $missingFiles));
                return 1;
            }

            $this->info("✅ All required agent files present");
            $this->info("🚀 UpdateAgentJob would be dispatched for VPS #{$vps->id}");
            
            return 0;
        }

        // Dispatch actual job
        $this->info("🚀 Dispatching UpdateAgentJob for VPS #{$vps->id}");
        
        try {
            UpdateAgentJob::dispatch($vps->id);
            $this->info("✅ UpdateAgentJob dispatched successfully");
            $this->info("📊 Monitor progress in logs: tail -f storage/logs/laravel.log");
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to dispatch UpdateAgentJob: {$e->getMessage()}");
            return 1;
        }

        return 0;
    }
}
