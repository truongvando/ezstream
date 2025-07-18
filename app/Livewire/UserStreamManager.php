<?php

namespace App\Livewire;

use App\Models\StreamConfiguration;
use App\Models\UserFile;
use App\Services\Stream\StreamAllocation;
use App\Services\StreamProgressService;
use App\Jobs\StartMultistreamJob;
use App\Jobs\StopMultistreamJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Livewire\Component;
use Livewire\WithPagination;

class UserStreamManager extends BaseStreamManager
{
    // Override abstract methods for user permissions
    protected function canManageAllStreams(): bool
    {
        return false; // Users can only manage their own streams
    }

    protected function canForceStop(): bool
    {
        return false; // Users cannot force stop streams
    }

    protected function requiresSubscription(): bool
    {
        return true; // Users need active subscription
    }

    protected function getLayoutName(): string
    {
        return 'layouts.sidebar';
    }

    /**
     * Refresh streams method for polling
     */
    public function refreshStreams()
    {
        // This method is called by wire:poll.2s="refreshStreams"
        // Just refresh the component
        $this->render();
    }

    // All common properties and methods are now inherited from BaseStreamManager
    // Common methods inherited from BaseStreamManager

    public function store()
    {
        $this->validate();

        $rtmpUrl = $this->platform === 'custom' ? $this->rtmp_url : $this->getPlatformUrl($this->platform);

        // Prepare file list for JSON storage
        $selectedFiles = UserFile::whereIn('id', $this->user_file_ids)->get();
        $fileList = $selectedFiles->map(function ($file) {
            return [
                'file_id' => $file->id,
                'filename' => $file->original_name,
                'path' => $file->disk === 'google_drive' ? $file->google_drive_file_id : $file->path,
                'disk' => $file->disk,
                'size' => $file->size,
            ];
        })->toArray();

        // Auto-generate backup URL based on platform
        $backupRtmpUrl = $this->platform !== 'custom' ? $this->getPlatformBackupUrl($this->platform) : '';

        // Create stream configuration (VPS allocation will be handled by StartStreamJob)
        Auth::user()->streamConfigurations()->create([
            'title' => $this->title,
            'description' => $this->description,
            'video_source_path' => $fileList, // Store file list as array (auto-cast to JSON)
            'rtmp_url' => $rtmpUrl,
            'rtmp_backup_url' => $backupRtmpUrl, // Auto-generated backup
            'stream_key' => $this->stream_key,
            'status' => 'INACTIVE',
            'loop' => $this->loop,
            'scheduled_at' => $this->scheduled_at,
            'playlist_order' => $this->playlist_order,
            'keep_files_after_stop' => $this->keep_files_after_stop,
            'user_file_id' => $selectedFiles->first()->id, // Primary file for backward compatibility
        ]);

        $this->showCreateModal = false;
        session()->flash('success', 'Stream đã được tạo thành công. Nhấn "Bắt đầu Stream" để khởi động.');
    }

    public function edit(StreamConfiguration $stream)
    {
        if ($stream->user_id !== Auth::id()) {
            abort(403);
        }
        $this->resetValidation();
        $this->editingStream = $stream;
        $this->title = $stream->title;
        $this->description = $stream->description;
        
        // Parse file list from JSON or handle legacy single file
        try {
            // Check if video_source_path is already an array (from database cast) or needs JSON decoding
            if (is_array($stream->video_source_path)) {
                $fileList = $stream->video_source_path;
            } elseif (is_string($stream->video_source_path)) {
                $fileList = json_decode($stream->video_source_path, true);
            } else {
                $fileList = null;
            }
            
            if (is_array($fileList)) {
                $this->user_file_ids = collect($fileList)->pluck('file_id')->toArray();
            } else {
                // Legacy single file support
                $userFile = UserFile::where('path', $stream->video_source_path)
                                    ->where('user_id', Auth::id())
                                    ->first();
                $this->user_file_ids = $userFile ? [$userFile->id] : [];
            }
        } catch (\Exception $e) {
            // Fallback for legacy data
            $this->user_file_ids = $stream->user_file_id ? [$stream->user_file_id] : [];
        }
        
        // Detect platform from RTMP URL
        $this->platform = $this->detectPlatformFromUrl($stream->rtmp_url);
        $this->rtmp_url = $stream->rtmp_url;

        $this->stream_key = $stream->stream_key;
        
        // Load new feature fields
        $this->loop = $stream->loop ?? false;
        $this->scheduled_at = $stream->scheduled_at ? $stream->scheduled_at->format('Y-m-d\TH:i') : null;
        $this->playlist_order = $stream->playlist_order ?? 'sequential';

        $this->showEditModal = true;
    }

    public function update()
    {
        if ($this->editingStream->user_id !== Auth::id()) {
            abort(403);
        }
        $this->validate();
        
        $rtmpUrl = $this->platform === 'custom' ? $this->rtmp_url : $this->getPlatformUrl($this->platform);

        // Prepare file list for JSON storage
        $selectedFiles = UserFile::whereIn('id', $this->user_file_ids)->get();
        $fileList = $selectedFiles->map(function ($file) {
            return [
                'file_id' => $file->id,
                'filename' => $file->original_name,
                'path' => $file->disk === 'google_drive' ? $file->google_drive_file_id : $file->path,
                'disk' => $file->disk,
                'size' => $file->size,
            ];
        })->toArray();

        // Auto-generate backup URL based on platform
        $backupRtmpUrl = $this->platform !== 'custom' ? $this->getPlatformBackupUrl($this->platform) : '';

        $this->editingStream->update([
            'title' => $this->title,
            'description' => $this->description,
            'video_source_path' => $fileList, // Store file list as array (auto-cast to JSON)
            'rtmp_url' => $rtmpUrl,
            'rtmp_backup_url' => $backupRtmpUrl, // Auto-generated backup
            'stream_key' => $this->stream_key,
            'loop' => $this->loop,
            'scheduled_at' => $this->scheduled_at,
            'playlist_order' => $this->playlist_order,
            'keep_files_after_stop' => $this->keep_files_after_stop,
            'user_file_id' => $selectedFiles->first()->id,
        ]);

        // If stream is currently running, dispatch update job
        if ($this->editingStream->status === 'STREAMING') {
            \App\Jobs\UpdateMultistreamJob::dispatch($this->editingStream);
            session()->flash('success', 'Stream đã được cập nhật! Thay đổi sẽ có hiệu lực trong vài giây.');
        } else {
            session()->flash('success', 'Đã cập nhật cấu hình stream thành công.');
        }

        $this->showEditModal = false;
    }

    // confirmDelete and delete methods inherited from BaseStreamManager

    public function startStream($stream)
    {
        // Handle both StreamConfiguration object and ID
        if (is_numeric($stream)) {
            $streamId = $stream;
            $stream = StreamConfiguration::find($streamId);
            if (!$stream) {
                error_log("❌ Stream not found: {$streamId}");
                session()->flash('error', 'Stream không tồn tại.');
                return;
            }
        }

        // Log to both Laravel log and error_log for debugging
        $logMessage = "🎯 [UserStreamManager] startStream called - Stream ID: {$stream->id}, Status: {$stream->status}";
        Log::info($logMessage);
        error_log($logMessage);

        Log::info("🎯 [UserStreamManager] startStream called", [
            'stream_id' => $stream->id,
            'current_status' => $stream->status,
            'user_id' => Auth::id(),
            'stream_user_id' => $stream->user_id
        ]);

        if ($stream->user_id !== Auth::id()) {
            Log::warning("🚫 [UserStreamManager] Unauthorized access attempt", [
                'stream_id' => $stream->id,
                'auth_user' => Auth::id(),
                'stream_owner' => $stream->user_id
            ]);
            abort(403);
        }

        // Refresh stream to get latest status
        $stream->refresh();

        Log::info("🔄 [UserStreamManager] Stream refreshed", [
            'stream_id' => $stream->id,
            'status_after_refresh' => $stream->status
        ]);

        // Check if already starting/streaming
        if (in_array($stream->status, ['STARTING', 'STREAMING'])) {
            Log::warning("⚠️ [UserStreamManager] Stream already in progress", [
                'stream_id' => $stream->id,
                'current_status' => $stream->status
            ]);
            session()->flash('error', 'Stream đã đang được xử lý.');
            return;
        }

        $stream->update(['status' => 'STARTING']);

        Log::info("📤 [UserStreamManager] Dispatching StartMultistreamJob", [
            'stream_id' => $stream->id
        ]);

        StartMultistreamJob::dispatch($stream);
        session()->flash('message', 'Lệnh bắt đầu stream đã được gửi đi.');
    }


    public function stopStream(StreamConfiguration $stream)
    {
        if ($stream->user_id !== Auth::id()) {
            abort(403);
        }
        $stream->update(['status' => 'STOPPING']);
        StopMultistreamJob::dispatch($stream);
        session()->flash('message', 'Lệnh dừng stream đã được gửi đi.');
    }

    // openQuickStreamModal and updateQuickPlatformUrl methods inherited from BaseStreamManager

    public function selectUploadedFile($fileId)
    {
        if (!in_array($fileId, $this->quickSelectedFiles)) {
            $this->quickSelectedFiles[] = $fileId;
        }
        $this->dispatch('refreshComponent');
    }

    public function createQuickStream()
    {
        Log::info('🚀 [QuickStream] Starting createQuickStream', [
            'quickTitle' => $this->quickTitle,
            'quickPlatform' => $this->quickPlatform,
            'quickSelectedFiles' => $this->quickSelectedFiles,
            'video_source_id' => $this->video_source_id,
        ]);

        $this->validate([
            'quickTitle' => 'required|string|max:255',
            'quickDescription' => 'nullable|string',
            'quickPlatform' => 'required|string',
            'quickRtmpUrl' => 'required_if:quickPlatform,custom|nullable|url',
            'quickStreamKey' => 'required|string',
        ]);

        $user = Auth::user();
        $userFiles = collect();

        // Check if we have uploaded file (from quick upload)
        if (!empty($this->video_source_id)) {
            $uploadedFile = $user->files()->where('id', $this->video_source_id)->first();
            if ($uploadedFile) {
                $userFiles->push($uploadedFile);
            }
        }

        // Check if we have selected files from library
        if (!empty($this->quickSelectedFiles)) {
            $libraryFiles = $user->files()->whereIn('id', $this->quickSelectedFiles)->get();
            $userFiles = $userFiles->merge($libraryFiles);
        }

        // Validate that we have at least one file
        if ($userFiles->isEmpty()) {
            session()->flash('error', 'Vui lòng chọn ít nhất một video hoặc upload file mới.');
            return;
        }

        // Mark files for auto-deletion
        foreach ($userFiles as $userFile) {
            $userFile->update([
                'auto_delete_after_stream' => true,
                'scheduled_deletion_at' => now()->addDays(1) // Grace period
            ]);
        }

        // Prepare file list for stream
        $fileList = [];
        foreach ($userFiles as $index => $userFile) {
            $fileList[] = [
                'file_id' => $userFile->id,
                'filename' => $userFile->original_name,
                'order' => $index + 1
            ];
        }

        // Get platform URLs
        $rtmpUrl = $this->quickPlatform === 'custom' ? $this->quickRtmpUrl : $this->getPlatformUrl($this->quickPlatform);
        $backupRtmpUrl = $this->getPlatformBackupUrl($this->quickPlatform);

        // Create quick stream configuration
        $stream = $userFiles->first()->user->streamConfigurations()->create([
            'title' => $this->quickTitle,
            'description' => $this->quickDescription,
            'video_source_path' => $fileList,
            'rtmp_url' => $rtmpUrl,
            'rtmp_backup_url' => $backupRtmpUrl,
            'stream_key' => $this->quickStreamKey,
            'status' => 'INACTIVE',
            'loop' => $this->quickLoop,
            'playlist_order' => 'sequential',
            'is_quick_stream' => true,
            'user_file_id' => $userFiles->first()->id
        ]);

        // Immediately dispatch the job to start the stream
        StartMultistreamJob::dispatch($stream);

        Log::info("User Quick Stream created and job dispatched", [
            'stream_id' => $stream->id,
            'user_id' => $userFiles->first()->user_id
        ]);

        $this->showQuickStreamModal = false;
        $this->resetQuickStreamForm();

        session()->flash('message', '🚀 Quick Stream đã được tạo và lệnh bắt đầu đã được gửi đi!');
    }

    public $quickUploadedFileId = null;

    private function getQuickUploadedFileId()
    {
        // Return the file ID set by JavaScript
        return $this->quickUploadedFileId;
    }

    protected function resetQuickStreamForm()
    {
        $this->quickTitle = '';
        $this->quickDescription = '';
        $this->quickPlatform = 'youtube';
        $this->quickRtmpUrl = '';
        $this->quickStreamKey = '';
        $this->quickLoop = false;
        $this->quickPlaylistOrder = 'sequential';
        $this->quickEnableSchedule = false;
        $this->quickScheduledAt = '';
        $this->quickScheduledEnd = '';
        $this->quickSelectedFiles = [];
        $this->video_source_id = null;
    }

    public function closeModal()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->showDeleteModal = false;
        $this->showQuickStreamModal = false;
        $this->reset();
    }

    /**
     * Get real-time progress for a stream from Redis
     */
    public function getStreamProgress($streamId)
    {
        try {
            $progress = StreamProgressService::getProgress($streamId);

            if ($progress) {
                return [
                    'stage' => $progress['stage'] ?? 'starting',
                    'progress_percentage' => $progress['progress_percentage'] ?? 0,
                    'message' => $progress['message'] ?? 'Đang chuẩn bị...',
                    'details' => $progress['details'] ?? [],
                    'updated_at' => $progress['updated_at'] ?? now()->toISOString()
                ];
            }

            // Fallback: Check stream status
            $stream = StreamConfiguration::find($streamId);
            if ($stream) {
                switch ($stream->status) {
                    case 'STARTING':
                        return [
                            'stage' => 'starting',
                            'progress_percentage' => 10,
                            'message' => 'Đang chuẩn bị...',
                            'details' => [],
                            'updated_at' => now()->toISOString()
                        ];
                    case 'STREAMING':
                        return [
                            'stage' => 'streaming',
                            'progress_percentage' => 100,
                            'message' => 'Đang phát trực tiếp!',
                            'details' => [],
                            'updated_at' => now()->toISOString()
                        ];
                    default:
                        return null;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Error getting stream progress for stream {$streamId}: " . $e->getMessage());
            return null;
        }
    }

    public function render()
    {
        $streams = $this->getStreamsQuery()->latest()->paginate(10);

        // Polling for progress updates
        $streams->each(function ($stream) {
            if (in_array($stream->status, ['STARTING', 'STOPPING'])) {
                $progressData = $this->getStreamProgress($stream->id);
                $stream->progress_data = $progressData;
            }
        });

        $userFiles = $this->getUserFiles();

        return view('livewire.user-stream-manager', [
            'streams' => $streams,
            'userFiles' => $userFiles,
            'platforms' => $this->getPlatforms(),
            'isAdmin' => false, // User manager is not admin
        ])->layout($this->getLayoutName())
          ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Quản lý Stream</h1>');
    }
}
