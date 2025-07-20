<?php

namespace App\Console\Commands;

use App\Models\StreamConfiguration;
use App\Models\User;
use App\Jobs\StopMultistreamJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class StopExpiredUserStreams extends Command
{
    protected $signature = 'streams:stop-expired-users';
    protected $description = 'Stop all streams of users with expired subscriptions';

    public function handle()
    {
        $this->info('🔍 Checking for streams of expired users...');
        
        // Tìm users có subscription hết hạn
        $expiredUsers = User::whereHas('subscriptions', function($query) {
            $query->where('status', 'ACTIVE')
                  ->where('ends_at', '<=', now());
        })->get();
        
        if ($expiredUsers->isEmpty()) {
            $this->info('✅ No users with expired subscriptions found');
            return;
        }
        
        $this->info("📧 Found {$expiredUsers->count()} users with expired subscriptions");
        
        $stoppedCount = 0;
        $errorCount = 0;
        
        foreach ($expiredUsers as $user) {
            try {
                // Auto-expire subscriptions
                $expiredSubs = $user->subscriptions()
                    ->where('status', 'ACTIVE')
                    ->where('ends_at', '<=', now())
                    ->update(['status' => 'EXPIRED']);
                
                if ($expiredSubs > 0) {
                    $this->line("⏰ Expired {$expiredSubs} subscriptions for {$user->email}");
                }
                
                // Stop all active streams của user
                $activeStreams = StreamConfiguration::where('user_id', $user->id)
                    ->whereIn('status', ['STREAMING', 'STARTING', 'STOPPING'])
                    ->get();
                
                foreach ($activeStreams as $stream) {
                    try {
                        $this->line("🛑 Stopping stream #{$stream->id}: {$stream->title}");
                        
                        $stream->update([
                            'status' => 'STOPPING',
                            'error_message' => 'Subscription expired - auto-stopped'
                        ]);
                        
                        StopMultistreamJob::dispatch($stream);
                        $stoppedCount++;
                        
                    } catch (\Exception $e) {
                        $errorCount++;
                        $this->error("❌ Failed to stop stream #{$stream->id}: " . $e->getMessage());
                        
                        Log::error('Failed to stop expired user stream', [
                            'stream_id' => $stream->id,
                            'user_id' => $user->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("❌ Failed to process user {$user->email}: " . $e->getMessage());
                
                Log::error('Failed to process expired user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        $this->info("📊 Summary: {$stoppedCount} streams stopped, {$errorCount} errors");
        
        Log::info('Expired user streams cleanup completed', [
            'expired_users' => $expiredUsers->count(),
            'stopped_streams' => $stoppedCount,
            'errors' => $errorCount
        ]);
    }
}
