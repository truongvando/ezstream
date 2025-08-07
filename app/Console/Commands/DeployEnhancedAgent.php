<?php

namespace App\Console\Commands;

use App\Services\AgentEnhancementService;
use Illuminate\Console\Command;

class DeployEnhancedAgent extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'agent:deploy-enhanced 
                            {--vps-id= : Deploy to specific VPS ID}
                            {--check-capabilities : Check agent capabilities}
                            {--force : Force deployment without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Deploy enhanced agent v7.0 with playlist management capabilities';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 EZStream Enhanced Agent Deployment v7.0');
        $this->line('Features: Playlist Management, Quality Monitoring, Loop Detection');
        $this->line('');

        $agentService = app(AgentEnhancementService::class);

        // Check capabilities mode
        if ($this->option('check-capabilities')) {
            return $this->checkCapabilities($agentService);
        }

        // Specific VPS deployment
        if ($vpsId = $this->option('vps-id')) {
            return $this->deployToSpecificVps($agentService, (int) $vpsId);
        }

        // Deploy to all VPS
        return $this->deployToAllVps($agentService);
    }

    private function checkCapabilities(AgentEnhancementService $agentService)
    {
        $this->info('🔍 Checking agent capabilities...');

        $vpsList = \App\Models\VpsServer::where('status', 'ACTIVE')->get();

        if ($vpsList->isEmpty()) {
            $this->warn('No active VPS servers found');
            return Command::SUCCESS;
        }

        $this->table(
            ['VPS ID', 'Name', 'Status', 'Check Result'],
            $vpsList->map(function ($vps) use ($agentService) {
                $result = $agentService->checkAgentCapabilities($vps->id);
                return [
                    $vps->id,
                    $vps->name,
                    $vps->status,
                    $result['success'] ? '✅ Agent responding' : '❌ No response'
                ];
            })
        );

        return Command::SUCCESS;
    }

    private function deployToSpecificVps(AgentEnhancementService $agentService, int $vpsId)
    {
        $vps = \App\Models\VpsServer::find($vpsId);
        if (!$vps) {
            $this->error("VPS #{$vpsId} not found");
            return Command::FAILURE;
        }

        $this->info("🎯 Deploying to VPS #{$vpsId}: {$vps->name}");

        if (!$this->option('force')) {
            if (!$this->confirm('Continue with deployment?')) {
                $this->info('Deployment cancelled');
                return Command::SUCCESS;
            }
        }

        $this->line('📦 Storing enhanced agent in Redis...');
        $storeResult = $agentService->storeEnhancedAgent();
        
        if (!$storeResult['success']) {
            $this->error('Failed to store agent: ' . $storeResult['error']);
            return Command::FAILURE;
        }

        $this->info('✅ Agent stored in Redis');

        $this->line("📤 Sending deployment command to VPS #{$vpsId}...");
        $deployResult = $agentService->deployToVps($vpsId);

        if ($deployResult['success']) {
            $this->info("✅ Deployment command sent successfully (subscribers: {$deployResult['subscribers']})");
            $this->line('');
            $this->info('🎉 Enhanced agent deployment initiated!');
            $this->line('The agent will update automatically and restart with new capabilities.');
        } else {
            $this->error('Failed to deploy: ' . $deployResult['error']);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function deployToAllVps(AgentEnhancementService $agentService)
    {
        $vpsList = \App\Models\VpsServer::where('status', 'ACTIVE')->get();

        if ($vpsList->isEmpty()) {
            $this->warn('No active VPS servers found');
            return Command::SUCCESS;
        }

        $this->info("🌐 Deploying to {$vpsList->count()} active VPS servers");
        $this->table(
            ['VPS ID', 'Name', 'Status', 'Current Streams'],
            $vpsList->map(function ($vps) {
                return [
                    $vps->id,
                    $vps->name,
                    $vps->status,
                    $vps->current_streams ?? 0
                ];
            })
        );

        if (!$this->option('force')) {
            if (!$this->confirm('Continue with deployment to all VPS?')) {
                $this->info('Deployment cancelled');
                return Command::SUCCESS;
            }
        }

        $this->line('📦 Storing enhanced agent in Redis...');
        $progressBar = $this->output->createProgressBar(3);

        // Step 1: Store agent
        $progressBar->advance();
        $storeResult = $agentService->storeEnhancedAgent();
        
        if (!$storeResult['success']) {
            $progressBar->finish();
            $this->line('');
            $this->error('Failed to store agent: ' . $storeResult['error']);
            return Command::FAILURE;
        }

        // Step 2: Deploy to all VPS
        $progressBar->advance();
        $this->line('');
        $this->info('✅ Agent stored in Redis');
        $this->line('📤 Deploying to all VPS servers...');

        $deployResult = $agentService->deployToAllVps();

        // Step 3: Show results
        $progressBar->advance();
        $progressBar->finish();
        $this->line('');

        if ($deployResult['success']) {
            $this->info("✅ Deployment commands sent to {$deployResult['deployed_count']} VPS servers");
            
            // Show detailed results
            $this->line('');
            $this->info('📊 Deployment Results:');
            
            $results = [];
            foreach ($deployResult['results'] as $vpsId => $result) {
                $vps = $vpsList->firstWhere('id', $vpsId);
                $results[] = [
                    $vpsId,
                    $vps ? $vps->name : 'Unknown',
                    $result['success'] ? '✅ Sent' : '❌ Failed',
                    $result['success'] ? ($result['subscribers'] ?? 0) . ' subscribers' : ($result['error'] ?? 'Unknown error')
                ];
            }

            $this->table(['VPS ID', 'Name', 'Status', 'Details'], $results);

            $this->line('');
            $this->info('🎉 Enhanced agent deployment completed!');
            $this->line('Agents will update automatically and restart with new capabilities:');
            $this->line('  • Playlist Management');
            $this->line('  • Quality Monitoring');
            $this->line('  • Loop Detection');
            $this->line('  • Enhanced Error Recovery');

        } else {
            $this->error('Deployment failed: ' . $deployResult['error']);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
