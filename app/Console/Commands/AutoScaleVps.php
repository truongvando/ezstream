<?php

namespace App\Console\Commands;

use App\Services\VpsScalingService;
use Illuminate\Console\Command;

class AutoScaleVps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vps:auto-scale {--dry-run : Show what would be done without executing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically scale VPS nodes based on current load and capacity';

    private VpsScalingService $scalingService;

    public function __construct(VpsScalingService $scalingService)
    {
        parent::__construct();
        $this->scalingService = $scalingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Analyzing VPS scaling needs...');

        // Get current metrics
        $metrics = $this->scalingService->getSystemMetrics();

        $this->displayMetrics($metrics);

        // Check scaling needs
        $analysis = $this->scalingService->checkScalingNeeds();

        $this->displayRecommendations($analysis['recommendations']);

        if (empty($analysis['recommendations'])) {
            $this->info('✅ No scaling actions needed at this time.');
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No actions will be executed');
            return 0;
        }

        // Confirm before executing
        if (!$this->confirm('Execute scaling actions?')) {
            $this->info('Scaling cancelled by user.');
            return 0;
        }

        // Execute auto-scaling
        $this->info('🚀 Executing auto-scaling...');
        $result = $this->scalingService->autoScale();

        $this->displayResults($result['actions_taken']);

        return 0;
    }

    private function displayMetrics(array $metrics): void
    {
        $this->info('📊 Current VPS Metrics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total VPS', $metrics['total_vps']],
                ['Active VPS', $metrics['active_vps']],
                ['Multistream VPS', $metrics['multistream_vps']],
                ['Total Capacity', $metrics['total_capacity'] . ' streams'],
                ['Used Capacity', $metrics['used_capacity'] . ' streams'],
                ['Available Capacity', $metrics['available_capacity'] . ' streams'],
                ['Capacity Usage', $metrics['capacity_usage'] . '%'],
                ['Idle VPS', $metrics['idle_vps_count']],
                ['Failed VPS', $metrics['failed_vps_count']],
                ['Average Load', $metrics['average_load'] . '%'],
            ]
        );
    }

    private function displayRecommendations(array $recommendations): void
    {
        if (empty($recommendations)) {
            return;
        }

        $this->info('💡 Scaling Recommendations:');

        foreach ($recommendations as $rec) {
            $priority = $rec['priority'] === 'high' ? '🔴' : '🟡';
            $this->line("{$priority} {$rec['action']}: {$rec['reason']}");
        }

        $this->newLine();
    }

    private function displayResults(array $actions): void
    {
        if (empty($actions)) {
            $this->info('No actions were taken.');
            return;
        }

        $this->info('📋 Scaling Actions Executed:');

        foreach ($actions as $action) {
            $status = $action['status'] === 'success' ? '✅' : '❌';
            $this->line("{$status} {$action['action']}: " . ($action['message'] ?? $action['error'] ?? 'Unknown result'));
        }
    }
}
