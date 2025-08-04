<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class BunnyWebhookController extends Controller
{
    /**
     * Handle BunnyCDN Stream Library webhooks
     */
    public function handleStreamWebhook(Request $request)
    {
        try {
            // Verify webhook signature
            if (!$this->verifyWebhookSignature($request)) {
                Log::warning('Invalid BunnyCDN webhook signature', [
                    'ip' => $request->ip(),
                    'headers' => $request->headers->all()
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $payload = $request->all();
            $eventType = $payload['EventType'] ?? null;
            $videoId = $payload['VideoGuid'] ?? null;

            Log::info('BunnyCDN webhook received', [
                'event_type' => $eventType,
                'video_id' => $videoId,
                'payload' => $payload
            ]);

            if (!$videoId) {
                Log::warning('BunnyCDN webhook missing video ID');
                return response()->json(['error' => 'Missing video ID'], 400);
            }

            // Find UserFile by stream_video_id
            $userFile = UserFile::where('stream_video_id', $videoId)->first();
            if (!$userFile) {
                Log::warning('UserFile not found for video ID', ['video_id' => $videoId]);
                return response()->json(['error' => 'File not found'], 404);
            }

            // Handle different event types
            switch ($eventType) {
                case 'video.uploaded':
                    $this->handleVideoUploaded($userFile, $payload);
                    break;

                case 'video.encoding.started':
                    $this->handleEncodingStarted($userFile, $payload);
                    break;

                case 'video.encoding.completed':
                    $this->handleEncodingCompleted($userFile, $payload);
                    break;

                case 'video.encoding.failed':
                    $this->handleEncodingFailed($userFile, $payload);
                    break;

                default:
                    Log::info('Unhandled BunnyCDN webhook event', ['event_type' => $eventType]);
            }

            // Dispatch Livewire refresh event
            $this->notifyUser($userFile);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('BunnyCDN webhook processing failed: ' . $e->getMessage(), [
                'exception' => $e,
                'payload' => $request->all()
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Verify webhook signature from BunnyCDN
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $webhookSecret = config('bunnycdn.webhook_secret');
        if (!$webhookSecret) {
            Log::warning('BunnyCDN webhook secret not configured');
            return true; // Allow if not configured (for development)
        }

        $signature = $request->header('X-Bunny-Signature');
        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Handle video uploaded event
     */
    private function handleVideoUploaded(UserFile $userFile, array $payload): void
    {
        $metadata = $userFile->stream_metadata ?? [];
        $metadata['processing_status'] = 'uploaded';
        $metadata['uploaded_at'] = now()->toISOString();
        $metadata['bunny_event'] = $payload;

        $userFile->update(['stream_metadata' => $metadata]);

        Log::info('Video uploaded to Stream Library', [
            'file_id' => $userFile->id,
            'video_id' => $userFile->stream_video_id
        ]);
    }

    /**
     * Handle encoding started event
     */
    private function handleEncodingStarted(UserFile $userFile, array $payload): void
    {
        $metadata = $userFile->stream_metadata ?? [];
        $metadata['processing_status'] = 'processing';
        $metadata['encoding_started_at'] = now()->toISOString();
        $metadata['encoding_progress'] = 0;

        $userFile->update(['stream_metadata' => $metadata]);

        Log::info('Video encoding started', [
            'file_id' => $userFile->id,
            'video_id' => $userFile->stream_video_id
        ]);
    }

    /**
     * Handle encoding completed event
     */
    private function handleEncodingCompleted(UserFile $userFile, array $payload): void
    {
        $metadata = $userFile->stream_metadata ?? [];
        $metadata['processing_status'] = 'completed';
        $metadata['encoding_completed_at'] = now()->toISOString();
        $metadata['encoding_progress'] = 100;

        // Update HLS URLs if provided
        if (isset($payload['VideoLibraryId']) && isset($payload['VideoGuid'])) {
            $cdnHostname = config('bunnycdn.stream_cdn_hostname');
            $videoId = $payload['VideoGuid'];

            $metadata['hls_url'] = "https://{$cdnHostname}/{$videoId}/playlist.m3u8";
            $metadata['mp4_url'] = "https://{$cdnHostname}/{$videoId}/play_720p.mp4";
            $metadata['thumbnail_url'] = "https://{$cdnHostname}/{$videoId}/thumbnail.jpg";

            // Update path to HLS URL for streaming
            $userFile->path = $metadata['hls_url'];
        }

        $userFile->update([
            'stream_metadata' => $metadata,
            'path' => $userFile->path
        ]);

        Log::info('Video encoding completed', [
            'file_id' => $userFile->id,
            'video_id' => $userFile->stream_video_id,
            'hls_url' => $metadata['hls_url'] ?? null
        ]);

        // Check if this file is part of any pending streams
        $this->checkPendingStreams($userFile);
    }

    /**
     * Check if completed file enables any pending streams
     */
    private function checkPendingStreams(UserFile $userFile): void
    {
        try {
            // Find streams that use this file and are waiting for processing
            $pendingStreams = \App\Models\StreamConfiguration::where('user_id', $userFile->user_id)
                ->where('status', 'waiting_for_processing')
                ->whereHas('videoFiles', function($query) use ($userFile) {
                    $query->where('user_files.id', $userFile->id);
                })
                ->get();

            foreach ($pendingStreams as $stream) {
                // Check if all files in this stream are ready
                $allFilesReady = $this->areAllFilesReady($stream);

                if ($allFilesReady) {
                    Log::info('All files ready for stream, enabling stream', [
                        'stream_id' => $stream->id,
                        'user_id' => $userFile->user_id
                    ]);

                    // Update stream status to ready
                    $stream->update([
                        'status' => 'ready',
                        'status_message' => 'All files processed, ready to stream'
                    ]);

                    // Optionally auto-start stream if configured
                    if ($stream->auto_start_when_ready) {
                        $this->autoStartStream($stream);
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error('Failed to check pending streams: ' . $e->getMessage());
        }
    }

    /**
     * Check if all files in stream are ready for streaming
     */
    private function areAllFilesReady(\App\Models\StreamConfiguration $stream): bool
    {
        $files = $stream->videoFiles;

        foreach ($files as $file) {
            if ($file->stream_video_id) {
                // Stream Library file - check processing status
                $processingStatus = $file->stream_metadata['processing_status'] ?? 'unknown';
                if ($processingStatus !== 'completed') {
                    return false;
                }
            }
            // Regular files are always ready
        }

        return true;
    }

    /**
     * Auto-start stream when all files are ready
     */
    private function autoStartStream(\App\Models\StreamConfiguration $stream): void
    {
        try {
            // Dispatch StartMultistreamJob
            \App\Jobs\StartMultistreamJob::dispatch($stream)
                ->onQueue('streams');

            Log::info('Auto-started stream after file processing completed', [
                'stream_id' => $stream->id
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to auto-start stream: ' . $e->getMessage());
        }
    }

    /**
     * Handle encoding failed event
     */
    private function handleEncodingFailed(UserFile $userFile, array $payload): void
    {
        $metadata = $userFile->stream_metadata ?? [];
        $metadata['processing_status'] = 'failed';
        $metadata['encoding_failed_at'] = now()->toISOString();
        $metadata['error_message'] = $payload['ErrorMessage'] ?? 'Encoding failed';

        $userFile->update(['stream_metadata' => $metadata]);

        Log::error('Video encoding failed', [
            'file_id' => $userFile->id,
            'video_id' => $userFile->stream_video_id,
            'error' => $metadata['error_message']
        ]);
    }

    /**
     * Notify user about file status update
     */
    private function notifyUser(UserFile $userFile): void
    {
        try {
            // Dispatch Livewire event to refresh file list
            if (class_exists('\Livewire\Livewire')) {
                // This will be picked up by any Livewire components listening
                event(new \App\Events\FileStatusUpdated($userFile));
            }

            // You could also use WebSocket/Pusher here for real-time updates
            
        } catch (\Exception $e) {
            Log::warning('Failed to notify user about file update: ' . $e->getMessage());
        }
    }

    /**
     * Test webhook endpoint
     */
    public function testWebhook(Request $request)
    {
        Log::info('BunnyCDN webhook test received', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'body' => $request->all()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook test received',
            'timestamp' => now()->toISOString()
        ]);
    }
}
