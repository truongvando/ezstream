<?php

namespace App\Console\Commands;

use App\Jobs\UpdateStreamStatusJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SubscribeToStreamStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:subscribe-stream-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Subscribe to a Redis channel to receive stream status updates';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $channel = 'stream-status';
        $this->info("Subscribing to [{$channel}] channel...");
        
        try {
            Redis::subscribe([$channel], function ($message) {
                $this->info('Received stream status: ' . $message);
                
                $data = json_decode($message, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    // Dữ liệu hợp lệ, đẩy vào queue để xử lý
                    UpdateStreamStatusJob::dispatch($data);
                } else {
                    Log::warning("Received invalid JSON on {$this->signature} channel.", ['message' => $message]);
                }
            });
        } catch (\Exception $e) {
            Log::error('Redis subscription failed for stream status.', ['error' => $e->getMessage()]);
            $this->error('Subscription failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
} 