<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StreamConfiguration;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StreamWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            // Validate required fields
            $streamId = $request->input('stream_id');
            $status = $request->input('status');
            $message = $request->input('message');
            $webhookSecret = $request->header('X-Webhook-Secret');

            if (!$streamId || !$status || !$webhookSecret) {
                return response()->json(['error' => 'Missing required fields'], 400);
            }

            // Find stream
            $stream = StreamConfiguration::find($streamId);
            if (!$stream) {
                Log::warning("Webhook for unknown stream", ['stream_id' => $streamId]);
                return response()->json(['error' => 'Stream not found'], 404);
            }

            // Validate webhook secret (check recent secrets to handle restarts)
            $validSecret = false;
            $currentTime = time();
            
            // Check secrets from last 2 hours (in case of restarts)
            for ($i = 0; $i < 7200; $i += 60) { // Check every minute for last 2 hours
                $testTime = $currentTime - $i;
                $secretKey = "webhook_secret_{$streamId}_{$testTime}";
                $expectedSecret = cache($secretKey);
                
                if ($expectedSecret && $webhookSecret === $expectedSecret) {
                    $validSecret = true;
                    break;
                }
            }
            
            if (!$validSecret) {
                Log::warning("Invalid webhook secret", [
                    'stream_id' => $streamId,
                    'provided_secret' => $webhookSecret
                ]);
                return response()->json(['error' => 'Invalid webhook secret'], 403);
            }

            Log::info("Stream webhook received", [
                'stream_id' => $streamId,
                'status' => $status,
                'message' => $message
            ]);

            // Update stream status based on webhook
            $this->updateStreamStatus($stream, $status, $message, $request);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error("Webhook processing error", [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    protected function updateStreamStatus(StreamConfiguration $stream, string $status, string $message, Request $request)
    {
        $updateData = [
            'output_log' => $message,
            'last_status_update' => now(),
        ];

        switch ($status) {
            case 'DOWNLOADING':
                $updateData['status'] = 'STARTING';
                break;

            case 'STREAMING':
                $updateData['status'] = 'STREAMING';
                $newPid = $request->input('new_pid');
                if ($newPid) {
                    $updateData['ffmpeg_pid'] = $newPid;
                }
                break;

            case 'RECOVERING':
                $updateData['status'] = 'STREAMING';
                $updateData['output_log'] = 'Auto-recovery: ' . $message;
                
                // Send recovery notification to user
                $this->sendRecoveryNotification($stream, $message);
                break;

            case 'HEARTBEAT':
                // Just update last status time, don't change status
                $updateData = [
                    'last_status_update' => now(),
                    'output_log' => $message
                ];
                break;

            case 'COMPLETED':
                $updateData['status'] = 'COMPLETED';
                $updateData['last_stopped_at'] = now();
                $updateData['ffmpeg_pid'] = null;
                break;

            case 'STOPPED':
                $updateData['status'] = 'STOPPED';
                $updateData['last_stopped_at'] = now();
                $updateData['ffmpeg_pid'] = null;
                break;

            case 'ERROR':
                $updateData['status'] = 'ERROR';
                $updateData['last_stopped_at'] = now();
                $updateData['ffmpeg_pid'] = null;
                
                // ✅ ENHANCED ERROR HANDLING
                $this->handleStreamError($stream, $message, $request);
                break;

            default:
                Log::warning("Unknown webhook status", ['status' => $status]);
                return;
        }

        $stream->update($updateData);

        Log::info("Stream status updated", [
            'stream_id' => $stream->id,
            'old_status' => $stream->getOriginal('status'),
            'new_status' => $updateData['status'],
            'message' => $message
        ]);
    }
    
    private function sendRecoveryNotification(StreamConfiguration $stream, string $message): void
    {
        $user = $stream->user;
        if ($user && $user->telegram_bot_token && $user->telegram_chat_id) {
            $notification = "🔄 *Stream đang phục hồi*\n\n";
            $notification .= "**Stream:** {$stream->title}\n";
            $notification .= "**Trạng thái:** Đang chuyển sang backup URL\n";
            $notification .= "**Chi tiết:** {$message}\n\n";
            $notification .= "💡 **Thông tin:** Hệ thống đã phát hiện sự cố và tự động chuyển sang đường truyền dự phòng. Stream sẽ tiếp tục hoạt động bình thường.\n\n";
            $notification .= "**Thời gian:** " . now()->format('d/m/Y H:i:s');
            
            (new TelegramNotificationService())->sendMessage(
                $user->telegram_bot_token, 
                $user->telegram_chat_id, 
                $notification
            );
        }
    }
    
    /**
     * Handle stream errors with classification and smart suggestions
     */
    private function handleStreamError(StreamConfiguration $stream, string $errorMessage, Request $request): void
    {
        // ✅ ERROR CLASSIFICATION
        $errorType = $this->classifyError($errorMessage);
        $suggestions = $this->getErrorSuggestions($errorType);
        $severity = $this->getErrorSeverity($errorType);
        
        Log::error("Stream error classified", [
            'stream_id' => $stream->id,
            'error_type' => $errorType,
            'severity' => $severity,
            'message' => $errorMessage
        ]);
        
        // ✅ CONDITIONAL NOTIFICATIONS
        if ($severity === 'critical') {
            // Immediate notification for critical errors
            $this->sendErrorNotification($stream, $errorType, $errorMessage, $suggestions);
            
            // Alert admin for critical errors
            $this->alertAdminForCriticalError($stream, $errorType, $errorMessage);
        } else {
            // Delayed notification for recoverable errors (give time for auto-recovery)
            \App\Jobs\DelayedStreamErrorNotificationJob::dispatch($stream, $this->formatErrorMessage($errorType, $errorMessage, $suggestions))
                ->delay(now()->addMinutes(2));
        }
        
        // ✅ AUTO-RECOVERY SUGGESTIONS
        if ($errorType === 'network_error' && empty($stream->rtmp_backup_url)) {
            $this->suggestBackupUrl($stream);
        }
        
        if ($errorType === 'auth_error') {
            $this->flagStreamForReview($stream);
        }
    }
    
    private function classifyError(string $errorMessage): string
    {
        $message = strtolower($errorMessage);
        
        if (strpos($message, 'network') !== false || strpos($message, 'connection') !== false) {
            return 'network_error';
        }
        
        if (strpos($message, 'authentication') !== false || strpos($message, 'auth') !== false || strpos($message, 'unauthorized') !== false) {
            return 'auth_error';
        }
        
        if (strpos($message, 'ffmpeg') !== false || strpos($message, 'conversion') !== false) {
            return 'ffmpeg_error';
        }
        
        if (strpos($message, 'resource') !== false || strpos($message, 'memory') !== false || strpos($message, 'space') !== false) {
            return 'resource_error';
        }
        
        if (strpos($message, 'stream') !== false || strpos($message, 'key') !== false) {
            return 'stream_error';
        }
        
        return 'unknown_error';
    }
    
    private function getErrorSeverity(string $errorType): string
    {
        $criticalErrors = ['auth_error', 'stream_error'];
        $recoverableErrors = ['network_error', 'ffmpeg_error', 'resource_error'];
        
        if (in_array($errorType, $criticalErrors)) {
            return 'critical';
        }
        
        if (in_array($errorType, $recoverableErrors)) {
            return 'recoverable';
        }
        
        return 'unknown';
    }
    
    private function getErrorSuggestions(string $errorType): array
    {
        $suggestions = [
            'network_error' => [
                'Kiểm tra kết nối internet của VPS',
                'Thử sử dụng backup RTMP URL',
                'Kiểm tra firewall và DNS settings'
            ],
            'auth_error' => [
                'Kiểm tra lại Stream Key',
                'Xác nhận RTMP URL đúng platform',
                'Kiểm tra account streaming platform'
            ],
            'ffmpeg_error' => [
                'Kiểm tra định dạng video files',
                'Thử restart stream',
                'Kiểm tra VPS resources'
            ],
            'resource_error' => [
                'Kiểm tra disk space VPS',
                'Kiểm tra RAM usage',
                'Contact admin để upgrade VPS'
            ],
            'stream_error' => [
                'Kiểm tra Stream Key và RTMP URL',
                'Kiểm tra platform streaming settings',
                'Thử tạo stream key mới'
            ]
        ];
        
        return $suggestions[$errorType] ?? ['Contact support team'];
    }
    
    private function sendErrorNotification(StreamConfiguration $stream, string $errorType, string $errorMessage, array $suggestions): void
    {
        $user = $stream->user;
        if ($user && $user->telegram_bot_token && $user->telegram_chat_id) {
            $message = "🚨 *Stream gặp lỗi!*\n\n";
            $message .= "**Stream:** {$stream->title}\n";
            $message .= "**Loại lỗi:** " . ucfirst(str_replace('_', ' ', $errorType)) . "\n";
            $message .= "**Chi tiết:** {$errorMessage}\n\n";
            
            $message .= "🛠️ **Hướng dẫn khắc phục:**\n";
            foreach ($suggestions as $suggestion) {
                $message .= "• {$suggestion}\n";
            }
            
            $message .= "\n**Thời gian:** " . now()->format('d/m/Y H:i:s');
            
            (new TelegramNotificationService())->sendMessage(
                $user->telegram_bot_token,
                $user->telegram_chat_id,
                $message
            );
        }
    }
    
    private function alertAdminForCriticalError(StreamConfiguration $stream, string $errorType, string $errorMessage): void
    {
        // Alert admin via Telegram for critical errors
        $admins = \App\Models\User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            if ($admin->telegram_bot_token && $admin->telegram_chat_id) {
                $message = "⚠️ *CRITICAL STREAM ERROR*\n\n";
                $message .= "**User:** {$stream->user->name}\n";
                $message .= "**Stream:** {$stream->title}\n";
                $message .= "**VPS:** {$stream->vpsServer->name}\n";
                $message .= "**Error Type:** {$errorType}\n";
                $message .= "**Message:** {$errorMessage}\n";
                $message .= "**Time:** " . now()->format('d/m/Y H:i:s');
                
                (new TelegramNotificationService())->sendMessage(
                    $admin->telegram_bot_token,
                    $admin->telegram_chat_id,
                    $message
                );
            }
        }
    }
    
    private function formatErrorMessage(string $errorType, string $errorMessage, array $suggestions): string
    {
        $message = "🚨 *Stream Error Report*\n\n";
        $message .= "**Error Type:** " . ucfirst(str_replace('_', ' ', $errorType)) . "\n";
        $message .= "**Details:** {$errorMessage}\n\n";
        $message .= "**Suggestions:**\n";
        foreach ($suggestions as $suggestion) {
            $message .= "• {$suggestion}\n";
        }
        return $message;
    }
    
    private function suggestBackupUrl(StreamConfiguration $stream): void
    {
        // Log suggestion for admin to add backup URL
        Log::info("Stream needs backup URL", [
            'stream_id' => $stream->id,
            'user_id' => $stream->user_id,
            'suggestion' => 'Add backup RTMP URL for better reliability'
        ]);
    }
    
    private function flagStreamForReview(StreamConfiguration $stream): void
    {
        // Flag stream for admin review
        $stream->update([
            'output_log' => $stream->output_log . ' [FLAGGED FOR REVIEW]'
        ]);
        
        Log::warning("Stream flagged for review", [
            'stream_id' => $stream->id,
            'reason' => 'Authentication error - manual review needed'
        ]);
    }
}
