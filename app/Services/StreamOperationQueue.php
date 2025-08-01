<?php

namespace App\Services;

use App\Models\StreamConfiguration;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class StreamOperationQueue
{
    private const OPERATION_TTL = 300; // 5 minutes
    private const MAX_RETRIES = 3;

    /**
     * Queue a stream operation with conflict detection
     */
    public static function queueOperation(int $streamId, string $operation, array $data = []): bool
    {
        $operationId = uniqid("{$operation}_{$streamId}_", true);
        $queueKey = "stream_operations:{$streamId}";
        
        // Check for conflicting operations
        if (self::hasConflictingOperation($streamId, $operation)) {
            Log::warning("⚠️ [StreamQueue] Conflicting operation detected for stream #{$streamId}: {$operation}");
            return false;
        }

        $operationData = [
            'id' => $operationId,
            'stream_id' => $streamId,
            'operation' => $operation,
            'data' => $data,
            'status' => 'QUEUED',
            'created_at' => now()->toISOString(),
            'retry_count' => 0
        ];

        // Add to queue with priority (START > STOP > UPDATE)
        $priority = self::getOperationPriority($operation);
        Redis::zadd($queueKey, $priority, json_encode($operationData));
        
        // Set TTL for the queue
        Redis::expire($queueKey, self::OPERATION_TTL);

        Log::info("📝 [StreamQueue] Queued operation {$operation} for stream #{$streamId} (ID: {$operationId})");
        return true;
    }

    /**
     * Process next operation for a stream
     */
    public static function processNext(int $streamId): ?array
    {
        $queueKey = "stream_operations:{$streamId}";
        
        // Get highest priority operation
        $operations = Redis::zrevrange($queueKey, 0, 0, 'WITHSCORES');
        if (empty($operations)) {
            return null;
        }

        $operationJson = array_keys($operations)[0];
        $operation = json_decode($operationJson, true);

        if (!$operation) {
            Redis::zrem($queueKey, $operationJson);
            return null;
        }

        // Remove from queue
        Redis::zrem($queueKey, $operationJson);

        // Mark as processing
        $operation['status'] = 'PROCESSING';
        $operation['started_at'] = now()->toISOString();

        $processingKey = "stream_processing:{$streamId}";
        Redis::setex($processingKey, 300, json_encode($operation));

        Log::info("⚡ [StreamQueue] Processing operation {$operation['operation']} for stream #{$streamId}");
        return $operation;
    }

    /**
     * Mark operation as completed
     */
    public static function markCompleted(int $streamId, string $operationId, bool $success = true): void
    {
        $processingKey = "stream_processing:{$streamId}";
        $operationData = Redis::get($processingKey);

        if ($operationData) {
            $operation = json_decode($operationData, true);
            
            if ($operation && $operation['id'] === $operationId) {
                $operation['status'] = $success ? 'COMPLETED' : 'FAILED';
                $operation['completed_at'] = now()->toISOString();

                // Store in history
                $historyKey = "stream_operation_history:{$streamId}";
                Redis::lpush($historyKey, json_encode($operation));
                Redis::ltrim($historyKey, 0, 99); // Keep last 100 operations
                Redis::expire($historyKey, 86400); // 24 hours

                // Remove from processing
                Redis::del($processingKey);

                $status = $success ? '✅' : '❌';
                Log::info("{$status} [StreamQueue] Operation {$operation['operation']} completed for stream #{$streamId}");
            }
        }
    }

    /**
     * Mark operation as failed and retry if possible
     */
    public static function markFailed(int $streamId, string $operationId, string $error): bool
    {
        $processingKey = "stream_processing:{$streamId}";
        $operationData = Redis::get($processingKey);

        if (!$operationData) {
            return false;
        }

        $operation = json_decode($operationData, true);
        if (!$operation || $operation['id'] !== $operationId) {
            return false;
        }

        $operation['retry_count']++;
        $operation['last_error'] = $error;

        if ($operation['retry_count'] < self::MAX_RETRIES) {
            // Retry with exponential backoff
            $delay = min(pow(2, $operation['retry_count']) * 5, 60); // Max 60 seconds
            
            $operation['status'] = 'QUEUED';
            $operation['retry_at'] = now()->addSeconds($delay)->toISOString();

            $queueKey = "stream_operations:{$streamId}";
            $priority = self::getOperationPriority($operation['operation']) - $operation['retry_count'];
            Redis::zadd($queueKey, $priority, json_encode($operation));

            Redis::del($processingKey);

            Log::warning("🔄 [StreamQueue] Retrying operation {$operation['operation']} for stream #{$streamId} (attempt {$operation['retry_count']})");
            return true;
        } else {
            // Max retries exceeded
            self::markCompleted($streamId, $operationId, false);
            Log::error("❌ [StreamQueue] Operation {$operation['operation']} failed permanently for stream #{$streamId}: {$error}");
            return false;
        }
    }

    /**
     * Check for conflicting operations
     */
    private static function hasConflictingOperation(int $streamId, string $operation): bool
    {
        $queueKey = "stream_operations:{$streamId}";
        $processingKey = "stream_processing:{$streamId}";

        // Check if already processing
        if (Redis::exists($processingKey)) {
            return true;
        }

        // Check queue for conflicts
        $operations = Redis::zrange($queueKey, 0, -1);
        foreach ($operations as $operationJson) {
            $existingOp = json_decode($operationJson, true);
            if ($existingOp && self::areOperationsConflicting($operation, $existingOp['operation'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if two operations conflict
     */
    private static function areOperationsConflicting(string $op1, string $op2): bool
    {
        $conflicts = [
            'START_STREAM' => ['START_STREAM', 'STOP_STREAM'],
            'STOP_STREAM' => ['START_STREAM', 'STOP_STREAM'],
            'UPDATE_STREAM' => ['START_STREAM', 'STOP_STREAM']
        ];

        return in_array($op2, $conflicts[$op1] ?? []);
    }

    /**
     * Get operation priority (higher number = higher priority)
     */
    private static function getOperationPriority(string $operation): int
    {
        $priorities = [
            'STOP_STREAM' => 100,
            'START_STREAM' => 90,
            'UPDATE_STREAM' => 80,
            'RESTART_STREAM' => 85
        ];

        return $priorities[$operation] ?? 50;
    }

    /**
     * Get current operation status for a stream
     */
    public static function getStatus(int $streamId): array
    {
        $queueKey = "stream_operations:{$streamId}";
        $processingKey = "stream_processing:{$streamId}";

        $status = [
            'queued_operations' => [],
            'processing_operation' => null,
            'queue_length' => 0
        ];

        // Get processing operation
        $processingData = Redis::get($processingKey);
        if ($processingData) {
            $status['processing_operation'] = json_decode($processingData, true);
        }

        // Get queued operations
        $operations = Redis::zrevrange($queueKey, 0, -1, 'WITHSCORES');
        foreach ($operations as $operationJson => $priority) {
            $operation = json_decode($operationJson, true);
            if ($operation) {
                $status['queued_operations'][] = $operation;
            }
        }

        $status['queue_length'] = count($status['queued_operations']);
        return $status;
    }

    /**
     * Clear all operations for a stream
     */
    public static function clearOperations(int $streamId): void
    {
        $queueKey = "stream_operations:{$streamId}";
        $processingKey = "stream_processing:{$streamId}";

        Redis::del($queueKey);
        Redis::del($processingKey);

        Log::info("🧹 [StreamQueue] Cleared all operations for stream #{$streamId}");
    }
}
