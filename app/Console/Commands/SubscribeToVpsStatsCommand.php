<?php

namespace App\Console\Commands;

use App\Jobs\UpdateVpsStatsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SubscribeToVpsStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:subscribe-stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe to a Redis channel to receive VPS stats updates';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Subscribing to [vps-stats] channel...');

        try {
            // Use raw Predis client with proper config (same as StreamStatusListener)
            $redisConfig = config('database.redis.default');
            $rawRedis = new \Predis\Client([
                'scheme' => 'tcp',
                'host' => $redisConfig['host'],
                'port' => $redisConfig['port'],
                'password' => $redisConfig['password'],
                'username' => $redisConfig['username'],
                'database' => $redisConfig['database'],
                'timeout' => 30.0, // Longer connection timeout
                'read_write_timeout' => 0, // No timeout for pub/sub
                'persistent' => false,
            ]);

            $this->info("✅ Connected to Redis: {$redisConfig['host']}:{$redisConfig['port']}");

            $pubsub = $rawRedis->pubSubLoop();
            $pubsub->subscribe('vps-stats');

            $messageCount = 0;

            foreach ($pubsub as $message) {
                if ($message->kind === 'message') {
                    $messageCount++;
                    $this->info("📊 Received VPS stats #{$messageCount}: " . $message->payload);

                    $data = json_decode($message->payload, true);

                    if (json_last_error() === JSON_ERROR_NONE) {
                        // Dữ liệu hợp lệ, đẩy vào queue để xử lý
                        UpdateVpsStatsJob::dispatch($data);
                        $this->info("✅ UpdateVpsStatsJob dispatched for VPS #{$data['vps_id']}");
                    } else {
                        Log::warning('Received invalid JSON on vps-stats channel.', ['message' => $message->payload]);
                        $this->warn("⚠️ Invalid JSON received");
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Redis subscription failed.', ['error' => $e->getMessage()]);
            $this->error('Subscription failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
} 