<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CleanupSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:cleanup {--force : Force cleanup without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired and corrupted sessions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Starting session cleanup...');

        // Clean expired sessions (older than session lifetime)
        $sessionLifetime = config('session.lifetime') * 60; // Convert to seconds
        $expiredTime = Carbon::now()->subSeconds($sessionLifetime)->timestamp;

        $expiredCount = DB::table('sessions')
            ->where('last_activity', '<', $expiredTime)
            ->count();

        if ($expiredCount > 0) {
            if ($this->option('force') || $this->confirm("Found {$expiredCount} expired sessions. Delete them?")) {
                $deleted = DB::table('sessions')
                    ->where('last_activity', '<', $expiredTime)
                    ->delete();

                $this->info("✅ Deleted {$deleted} expired sessions");
            }
        } else {
            $this->info("✅ No expired sessions found");
        }

        // Clean orphaned sessions (user_id doesn't exist)
        $orphanedCount = DB::table('sessions')
            ->whereNotNull('user_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereRaw('users.id = sessions.user_id');
            })
            ->count();

        if ($orphanedCount > 0) {
            if ($this->option('force') || $this->confirm("Found {$orphanedCount} orphaned sessions. Delete them?")) {
                $deleted = DB::table('sessions')
                    ->whereNotNull('user_id')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('users')
                            ->whereRaw('users.id = sessions.user_id');
                    })
                    ->delete();

                $this->info("✅ Deleted {$deleted} orphaned sessions");
            }
        } else {
            $this->info("✅ No orphaned sessions found");
        }

        // Show current session stats
        $totalSessions = DB::table('sessions')->count();
        $activeSessions = DB::table('sessions')
            ->where('last_activity', '>=', Carbon::now()->subMinutes(30)->timestamp)
            ->count();

        $this->info("📊 Session Statistics:");
        $this->line("   Total sessions: {$totalSessions}");
        $this->line("   Active sessions (last 30 min): {$activeSessions}");

        $this->info("🎉 Session cleanup completed!");

        return 0;
    }
}
