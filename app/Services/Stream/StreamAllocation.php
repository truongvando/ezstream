<?php

namespace App\Services\Stream;

use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Jobs\StartMultistreamJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;

/**
 * Stream Allocation Service v3.0
 * Với Queue Management cho high-load scenarios
 */
class StreamAllocation
{
    // Ngưỡng tài nguyên để loại bỏ một VPS
    private const CPU_THRESHOLD = 80.0;
    private const RAM_THRESHOLD = 85.0;

    // Queue management constants
    private const QUEUE_KEY = 'stream_allocation_queue';
    private const HIGH_CPU_THRESHOLD = 90.0; // Ngưỡng để đẩy vào queue

    /**
     * 🚦 Main method: Assign stream to VPS or queue if overloaded
     */
    public function assignStreamToVps(StreamConfiguration $stream): array
    {
        Log::info("🚦 [StreamAllocation] Assigning stream #{$stream->id} to VPS");

        // Use distributed lock to prevent concurrent allocation conflicts
        return \App\Services\DistributedLock::execute(
            "stream_allocation_{$stream->id}",
            function() use ($stream) {
                // Check if we can find available VPS
                $vps = $this->findOptimalVps($stream);

                if ($vps) {
                    // Double-check VPS capacity with lock
                    $vps->refresh();
                    if ($vps->current_streams >= $vps->max_streams) {
                        Log::warning("⚠️ [StreamAllocation] VPS #{$vps->id} became full during allocation, retrying");
                        $vps = $this->findOptimalVps($stream);
                    }

                    if ($vps) {
                        // Atomic assignment with capacity increment
                        $vps->increment('current_streams');

                        $stream->update([
                            'vps_server_id' => $vps->id,
                            'status' => 'STARTING'
                        ]);

                        Log::info("✅ [StreamAllocation] Assigned stream #{$stream->id} to VPS #{$vps->id} (capacity: {$vps->current_streams}/{$vps->max_streams})");

                        return [
                            'success' => true,
                            'action' => 'assigned',
                            'vps_id' => $vps->id,
                            'message' => "Stream assigned to VPS #{$vps->id}"
                        ];
                    }
                }

                // No VPS available - add to queue
                Log::warning("⏳ [StreamAllocation] All VPS overloaded, queueing stream #{$stream->id}");

                $this->addToQueue($stream);

                return [
                    'success' => true,
                    'action' => 'queued',
                    'message' => 'Stream added to queue - will start when VPS capacity available'
                ];
            }
        );
    }

    public function findOptimalVps(StreamConfiguration $stream): ?VpsServer
    {
        Log::info("Finding optimal VPS for stream #{$stream->id} using real-time resource logic.");

        // 1. Lấy tất cả VPS đang hoạt động
        $activeVpsCollection = VpsServer::where('status', 'ACTIVE')->get();

        if ($activeVpsCollection->isEmpty()) {
            Log::warning("No active VPS servers found in the system.");
            return null;
        }

        // 2. Lấy thông số real-time và lọc ra các VPS "khỏe mạnh"
        $healthyVps = $activeVpsCollection->map(function ($vps) {
            $stats = $this->getVpsStatsFromRedis($vps->id);

            // VPS được coi là khỏe mạnh nếu có stats và CPU dưới 90%
            $isHealthy = $stats && $stats['cpu_usage'] < self::HIGH_CPU_THRESHOLD;

            if ($isHealthy) {
                // Thêm thông số CPU để sắp xếp
                $vps->current_cpu_usage = $stats['cpu_usage'];
                return $vps;
            }

            return null;
        })->filter(); // Loại bỏ các VPS không khỏe mạnh (null)

        if ($healthyVps->isEmpty()) {
            Log::warning("No healthy VPS available for stream #{$stream->id}. All servers are overloaded or offline.", [
                'total_active_vps' => $activeVpsCollection->count(),
                'vps_details' => $activeVpsCollection->map(function($vps) {
                    $stats = $this->getVpsStatsFromRedis($vps->id);
                    return [
                        'id' => $vps->id,
                        'name' => $vps->name,
                        'has_stats' => !is_null($stats),
                        'cpu_usage' => $stats['cpu_usage'] ?? 'N/A',
                        'ram_usage' => $stats['ram_usage'] ?? 'N/A',
                    ];
                })->toArray()
            ]);
            return null;
        }

        // 3. Sắp xếp các VPS khỏe mạnh theo mức sử dụng CPU tăng dần và chọn cái tốt nhất
        $bestVps = $healthyVps->sortBy('current_cpu_usage')->first();

        Log::info("Selected optimal VPS for stream #{$stream->id}", [
            'vps_id' => $bestVps->id,
            'vps_name' => $bestVps->name,
            'current_cpu_usage' => $bestVps->current_cpu_usage,
        ]);

        return $bestVps;
    }
    
    /**
     * Lấy thông số VPS mới nhất trực tiếp từ Redis cache.
     * Dữ liệu này được `agent.py` gửi về và được `UpdateVpsStatsJob` lưu trữ.
     */
    private function getVpsStatsFromRedis(int $vpsId): ?array
    {
        try {
            $statsJson = Redis::hget('vps_live_stats', $vpsId);

            if ($statsJson) {
                $statsData = json_decode($statsJson, true);
                return [
                    'cpu_usage' => $statsData['cpu_usage'] ?? 999,
                    'ram_usage' => $statsData['ram_usage'] ?? 999,
                ];
            }
        } catch (\Exception $e) {
            Log::error("Failed to get live stats for VPS #{$vpsId} from Redis.", ['error' => $e->getMessage()]);
        }
        
        // Trả về null nếu không có dữ liệu để VPS này bị loại
        return null;
    }

    /**
     * 📝 Add stream to queue when all VPS are overloaded
     */
    private function addToQueue(StreamConfiguration $stream): void
    {
        $queueData = [
            'stream_id' => $stream->id,
            'user_id' => $stream->user_id,
            'priority' => $this->calculatePriority($stream),
            'queued_at' => now()->timestamp
        ];

        // Add to Redis sorted set (sorted by priority)
        Redis::zadd(self::QUEUE_KEY, $queueData['priority'], json_encode($queueData));

        // Update stream status
        $stream->update([
            'status' => 'PENDING',
            'error_message' => 'Waiting in queue for available VPS capacity'
        ]);

        Log::info("📝 [StreamAllocation] Added stream #{$stream->id} to queue with priority {$queueData['priority']}");
    }

    /**
     * 🏆 Calculate stream priority (higher = more important)
     */
    private function calculatePriority(StreamConfiguration $stream): float
    {
        $priority = 1000; // Base priority

        // Premium users get higher priority
        if ($stream->user && $stream->user->subscription_type === 'premium') {
            $priority += 500;
        }

        // Scheduled streams get higher priority near their time
        if ($stream->scheduled_at) {
            $minutesUntilScheduled = now()->diffInMinutes($stream->scheduled_at, false);
            if ($minutesUntilScheduled <= 30 && $minutesUntilScheduled >= 0) {
                $priority += (30 - $minutesUntilScheduled) * 10; // Up to +300
            }
        }

        // Add timestamp for FIFO within same priority
        $priority += (1000000000 - now()->timestamp) / 1000000; // Small decimal for ordering

        return $priority;
    }

    /**
     * 🔄 Process queue - check for available VPS and start queued streams
     */
    public function processQueue(): void
    {
        Log::info("🔄 [StreamAllocation] Processing stream queue");

        // Get queued streams (highest priority first)
        $queuedItems = Redis::zrevrange(self::QUEUE_KEY, 0, -1, 'WITHSCORES');

        if (empty($queuedItems)) {
            Log::debug("📭 [StreamAllocation] Queue is empty");
            return;
        }

        $processedCount = 0;

        foreach ($queuedItems as $itemJson => $priority) {
            $item = json_decode($itemJson, true);
            $streamId = $item['stream_id'];

            $stream = StreamConfiguration::find($streamId);
            if (!$stream || $stream->status !== 'PENDING') {
                // Stream was deleted or status changed - remove from queue
                Redis::zrem(self::QUEUE_KEY, $itemJson);
                Log::info("🗑️ [StreamAllocation] Removed invalid stream #{$streamId} from queue");
                continue;
            }

            // Try to find available VPS
            $vps = $this->findOptimalVps($stream);

            if ($vps) {
                // Successfully found VPS - assign and start
                $stream->update([
                    'vps_server_id' => $vps->id,
                    'status' => 'STARTING'
                ]);

                // Remove from queue
                Redis::zrem(self::QUEUE_KEY, $itemJson);

                // Dispatch start job
                StartMultistreamJob::dispatch($stream);

                $processedCount++;
                Log::info("✅ [StreamAllocation] Started queued stream #{$streamId} on VPS #{$vps->id}");
            } else {
                // Still no capacity - stop processing (queue is sorted by priority)
                Log::debug("⏸️ [StreamAllocation] No capacity for stream #{$streamId}, stopping queue processing");
                break;
            }
        }

        if ($processedCount > 0) {
            Log::info("🎉 [StreamAllocation] Processed {$processedCount} streams from queue");
        }
    }

    /**
     * 📊 Get queue status for monitoring
     */
    public function getQueueStatus(): array
    {
        $queueSize = Redis::zcard(self::QUEUE_KEY);

        $queuedItems = Redis::zrevrange(self::QUEUE_KEY, 0, 9, 'WITHSCORES'); // Top 10

        $streams = [];
        foreach ($queuedItems as $itemJson => $priority) {
            $item = json_decode($itemJson, true);
            $stream = StreamConfiguration::with('user')->find($item['stream_id']);

            if ($stream) {
                $streams[] = [
                    'id' => $stream->id,
                    'title' => $stream->title,
                    'user' => $stream->user->name ?? 'Unknown',
                    'priority' => $priority,
                    'queued_at' => $item['queued_at'],
                    'waiting_time' => now()->timestamp - $item['queued_at']
                ];
            }
        }

        return [
            'total_queued' => $queueSize,
            'streams' => $streams
        ];
    }
}
