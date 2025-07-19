<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;

class TestSchedule extends Command
{
    protected $signature = 'test:schedule';
    protected $description = 'Test if schedule is working';

    public function handle()
    {
        $this->info('🧪 Testing Schedule System...');
        
        try {
            // Get schedule instance
            $schedule = app(Schedule::class);
            
            // Manually register our bank check command
            $schedule->command('bank:check-transactions')
                     ->everyMinute()
                     ->withoutOverlapping()
                     ->runInBackground();
            
            $this->info('✅ Manually registered bank:check-transactions');
            
            // Get reflection to check events
            $reflection = new \ReflectionClass($schedule);
            $eventsProperty = $reflection->getProperty('events');
            $eventsProperty->setAccessible(true);
            $events = $eventsProperty->getValue($schedule);
            
            $this->info("📊 Total scheduled events: " . count($events));
            
            foreach ($events as $event) {
                $this->line("  - " . $event->getSummaryForDisplay());
            }
            
            // Test running the schedule
            $this->info('🔄 Testing schedule run...');
            
            foreach ($events as $event) {
                if ($event->isDue(app())) {
                    $this->info("✅ Event is due: " . $event->getSummaryForDisplay());
                } else {
                    $this->comment("⏳ Event not due: " . $event->getSummaryForDisplay());
                }
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $this->line("Stack trace: " . $e->getTraceAsString());
        }
        
        return 0;
    }
}
