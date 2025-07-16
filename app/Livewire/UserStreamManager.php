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

class UserStreamManager extends Component
{
    use WithPagination;

    protected $listeners = ['refreshComponent' => '$refresh'];

    public function mount()
    {
        $this->cleanupHangingStreams();
    }

    /**
     * Cleanup streams that are stuck in STOPPING status
     */
    private function cleanupHangingStreams()
    {
        try {
            $timeout = 300; // 5 minutes
            $hangingStreams = StreamConfiguration::where('status', 'STOPPING')
                ->where('user_id', Auth::id()) // Only user's own streams
                ->where('updated_at', '<', now()->subSeconds($timeout))
                ->get();

            foreach ($hangingStreams as $stream) {
                $stuckDuration = now()->diffInSeconds($stream->updated_at);

                Log::warning("🔧 [UserStreamManager] Auto-fixing hanging stream #{$stream->id} for user #{$stream->user_id} stuck for {$stuckDuration}s");

                $stream->update([
                    'status' => 'INACTIVE',
                    'last_stopped_at' => now(),
                    'vps_server_id' => null,
                    'error_message' => "Auto-fixed: was stuck in STOPPING status for {$stuckDuration}s",
                ]);

                // Decrement VPS stream count if needed
                if ($stream->vps_server_id) {
                    $vps = $stream->vpsServer;
                    if ($vps && $vps->current_streams > 0) {
                        $vps->decrement('current_streams');
                    }
                }
            }

            if ($hangingStreams->count() > 0) {
                session()->flash('message', "Đã tự động sửa {$hangingStreams->count()} stream bị treo.");
            }

        } catch (\Exception $e) {
            Log::error("❌ [UserStreamManager] Failed to cleanup hanging streams: {$e->getMessage()}");
        }
    }

    public $showCreateModal = false;
    public $showEditModal = false;
    public $showDeleteModal = false;
    public $editingStream;
    public $deletingStream;

    // Filter properties
    public $filterStatus = '';

    // Form fields
    public $title, $description, $user_file_ids = [], $platform = 'youtube';
    public $rtmp_url, $stream_key;
    
    // New feature fields
    public $loop = false;
    public $enable_schedule = false;
    public $scheduled_at;
    public $scheduled_end;
    public $playlist_order = 'sequential'; // 'sequential' or 'random'
    public $keep_files_after_stop = false; // Keep downloaded files when stream stops

    protected function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_file_ids' => 'required|array|min:1',
            'user_file_ids.*' => 'exists:user_files,id',
            'platform' => 'required|string',
            'stream_key' => 'required|string',
            'rtmp_url' => 'required_if:platform,custom|nullable|url',
            'loop' => 'boolean',
            'scheduled_at' => 'nullable|date',
            'playlist_order' => 'required|in:sequential,random',
        ];
    }
    
    public function create()
    {
        Log::info('UserStreamManager::create() method called');
        
        $user = Auth::user();
        
        // Admin có thể tạo stream mà không cần kiểm tra subscription
        if (!$user->isAdmin()) {
            // Check if user has active subscription
            $activeSubscription = $user->subscriptions()->where('status', 'ACTIVE')->first();
            if (!$activeSubscription) {
                session()->flash('error', 'Bạn cần có gói dịch vụ đang hoạt động để tạo stream. Vui lòng mua gói dịch vụ trước.');
                return redirect()->route('billing.manager');
            }
        }
        
        $this->reset(['title', 'description', 'user_file_ids', 'platform', 'rtmp_url', 'stream_key', 'playlist_order']);
        $this->user_file_ids = [];
        $this->platform = 'youtube';
        $this->playlist_order = 'sequential';
        $this->showCreateModal = true;
        Log::info('showCreateModal set to true: ' . ($this->showCreateModal ? 'true' : 'false'));
    }

    public function updatedPlatform()
    {
        $this->rtmp_url = $this->getPlatformUrl($this->platform);
    }

    protected function getPlatformUrl($platform)
    {
        $platforms = [
            'youtube' => 'rtmp://a.rtmp.youtube.com/live2',
            'custom' => ''
        ];

        return $platforms[$platform] ?? '';
    }

    protected function getPlatformBackupUrl($platform)
    {
        $backupPlatforms = [
            'youtube' => 'rtmp://b.rtmp.youtube.com/live2',
            'custom' => '' // No backup for custom
        ];

        return $backupPlatforms[$platform] ?? '';
    }

    protected function detectPlatformFromUrl($url)
    {
        if (str_contains($url, 'youtube.com')) return 'youtube';
        return 'custom';
    }

    public function getPlatforms()
    {
        return [
            'youtube' => '📺 YouTube Live',
            'custom' => '⚙️ Custom RTMP'
        ];
    }

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

    public function confirmDelete(StreamConfiguration $stream)
    {
        if ($stream->user_id !== Auth::id()) {
            abort(403);
        }
        $this->deletingStream = $stream;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        if ($this->deletingStream->user_id !== Auth::id()) {
            abort(403);
        }

        // If stream is running, stop it first
        if ($this->deletingStream->status === 'STREAMING') {
            \App\Jobs\StopMultistreamJob::dispatch($this->deletingStream);
        }

        // Send cleanup command to VPS to remove downloaded files
        if ($this->deletingStream->vps_server_id) {
            \App\Jobs\CleanupStreamFilesJob::dispatch($this->deletingStream);
        }

        $this->deletingStream->delete();
        $this->showDeleteModal = false;
        session()->flash('success', 'Stream đã được xóa. Tất cả file liên quan cũng đã được dọn dẹp.');
    }

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

    public function closeModal()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->showDeleteModal = false;
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
        $streams = Auth::user()->streamConfigurations()->with('vpsServer')->paginate(10);
        $userFiles = Auth::user()->files()->where('status', 'ready')->get();

        // Add real-time progress to each stream
        foreach ($streams as $stream) {
            if (in_array($stream->status, ['STARTING', 'STREAMING'])) {
                $progress = $this->getStreamProgress($stream->id);
                if ($progress) {
                    $stream->progress_data = $progress;
                }
            }
        }

        return view('livewire.user-stream-manager', [
            'streams' => $streams,
            'userFiles' => $userFiles,
            'platforms' => $this->getPlatforms(),
            'isAdmin' => false, // User manager is not admin
        ])->layout('layouts.sidebar')
          ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Quản lý Stream</h1>');
    }
}
