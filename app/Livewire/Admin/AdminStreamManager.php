<?php

namespace App\Livewire\Admin;

use App\Models\StreamConfiguration;
use App\Models\User;
use App\Models\VpsServer;
use App\Services\StreamProgressService;
use App\Jobs\StartMultistreamJob;
use App\Jobs\StopMultistreamJob;
use App\Jobs\CleanupStreamFilesJob;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;
use App\Models\UserFile;

class AdminStreamManager extends Component
{
    use WithPagination;

    protected $listeners = ['refreshComponent' => '$refresh'];

    // Modals
    public $showEditModal = false;
    public $showDeleteModal = false;
    public $showCreateModal = false;
    
    // Models
    public $editingStream;
    public $deletingStream;

    // Form fields
    public $user_id, $title, $description, $rtmp_url, $stream_key;
    public $platform = 'youtube';
    public $user_file_ids = [];
    public $loop = false;
    public $enable_schedule = false;
    public $playlist_order = 'sequential';
    public $keep_files_after_stop = false;
    
    // Scheduling properties
    public $scheduled_at;
    public $scheduled_end;

    // Filters
    public $filterUserId = '';
    public $filterStatus = '';

    protected function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
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
    
    public function mount()
    {
        // Cleanup hanging streams first
        $this->cleanupHangingStreams();

        // Check if testing specific VPS
        if (request()->has('test_vps')) {
            $testVpsId = request()->get('test_vps');
            $testVps = VpsServer::find($testVpsId);
            if ($testVps && $testVps->status === 'ACTIVE') {
                session()->flash('info', "Test mode: Using VPS {$testVps->name} ({$testVps->ip_address})");

                // Auto open create modal for quick testing
                $this->showCreateModal = true;
                $this->create();
            }
        }
    }

    /**
     * Cleanup streams that are stuck in STOPPING status
     */
    private function cleanupHangingStreams()
    {
        try {
            $timeout = 300; // 5 minutes
            $hangingStreams = StreamConfiguration::where('status', 'STOPPING')
                ->where('updated_at', '<', now()->subSeconds($timeout))
                ->get();

            foreach ($hangingStreams as $stream) {
                $stuckDuration = now()->diffInSeconds($stream->updated_at);

                Log::warning("🔧 [AdminStreamManager] Auto-fixing hanging stream #{$stream->id} stuck for {$stuckDuration}s");

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
            Log::error("❌ [AdminStreamManager] Failed to cleanup hanging streams: {$e->getMessage()}");
        }
    }
    
    /**
     * Create new stream (Admin only)
     */
    public function create()
    {
        $this->resetValidation();
        $this->reset(['title', 'description', 'user_file_ids', 'platform', 'rtmp_url', 'stream_key', 'loop', 'scheduled_at', 'scheduled_end', 'enable_schedule', 'playlist_order']);
        $this->user_id = auth()->id(); // Default to admin
        $this->user_file_ids = [];
        $this->platform = 'youtube';
        $this->playlist_order = 'sequential';
        $this->showCreateModal = true;
    }

    public function closeModal()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->resetValidation();
    }
    
    /**
     * Store new stream
     */
    public function store()
    {
        $this->validate();
        
        $rtmpUrl = $this->platform === 'custom' ? $this->rtmp_url : $this->getPlatformUrl($this->platform);
        
        // Prepare file list for JSON storage
        $selectedFiles = \App\Models\UserFile::whereIn('id', $this->user_file_ids)->get();
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
        
        // Create stream configuration (VPS allocation service will assign VPS automatically)
        $stream = StreamConfiguration::create([
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'video_source_path' => $fileList, // Store file list as array (auto-cast to JSON)
            'rtmp_url' => $rtmpUrl,
            'rtmp_backup_url' => $backupRtmpUrl, // Auto-generated backup
            'stream_key' => $this->stream_key,
            'status' => 'INACTIVE',
            'loop' => $this->loop,
            'scheduled_at' => $this->enable_schedule ? $this->scheduled_at : null,
            'scheduled_end' => $this->enable_schedule ? $this->scheduled_end : null,
            'playlist_order' => $this->playlist_order,
            'user_file_id' => $selectedFiles->first()->id // Primary file for backward compatibility
        ]);
        
        $this->showCreateModal = false;
        session()->flash('success', 'Stream đã được tạo thành công. Nhấn "Bắt đầu Stream" để khởi động.');
    }

    public function edit(StreamConfiguration $stream)
    {
        if (!auth()->user()->isAdmin() && $stream->user_id !== Auth::id()) {
            abort(403);
        }
        $this->resetValidation();
        $this->editingStream = $stream;
        $this->user_id = $stream->user_id;
        $this->title = $stream->title;
        $this->description = $stream->description;
        
        // Parse file list from JSON or handle legacy single file
        try {
            // Check if video_source_path is already an array or needs JSON decoding
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
                $this->user_file_ids = $stream->user_file_id ? [$stream->user_file_id] : [];
            }
        } catch (\Exception $e) {
            // Fallback for legacy data
            $this->user_file_ids = $stream->user_file_id ? [$stream->user_file_id] : [];
        }
        
        $this->platform = $this->detectPlatformFromUrl($stream->rtmp_url);
        $this->rtmp_url = $stream->rtmp_url;
        $this->stream_key = $stream->stream_key;
        $this->loop = $stream->loop ?? false;
        $this->playlist_order = $stream->playlist_order ?? 'sequential';
        $this->scheduled_at = $stream->scheduled_at ? $stream->scheduled_at->format('Y-m-d\TH:i') : null;
        $this->scheduled_end = $stream->scheduled_end ? $stream->scheduled_end->format('Y-m-d\TH:i') : null;

        $this->showEditModal = true;
    }

    public function update()
    {
        if (!auth()->user()->isAdmin() && $this->editingStream->user_id !== Auth::id()) {
            abort(403);
        }
        $this->validate();
        
        $rtmpUrl = $this->platform === 'custom' ? $this->rtmp_url : $this->getPlatformUrl($this->platform);
        
        // Prepare file list for JSON storage (same logic as store method)
        $selectedFiles = \App\Models\UserFile::whereIn('id', $this->user_file_ids)->get();
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
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'video_source_path' => $fileList, // Store file list as array (auto-cast to JSON)
            'rtmp_url' => $rtmpUrl,
            'rtmp_backup_url' => $backupRtmpUrl, // Auto-generated backup
            'stream_key' => $this->stream_key,
            'loop' => $this->loop,
            'scheduled_at' => $this->enable_schedule ? $this->scheduled_at : null,
            'scheduled_end' => $this->enable_schedule ? $this->scheduled_end : null,
            'playlist_order' => $this->playlist_order,
            'user_file_id' => $selectedFiles->first()->id // Primary file for backward compatibility
        ]);

        $this->showEditModal = false;

        // If stream is currently running, offer to update live
        if (in_array($this->editingStream->status, ['STREAMING', 'ACTIVE'])) {
            session()->flash('info', 'Stream đã được cập nhật. Bạn có muốn áp dụng thay đổi cho stream đang chạy không?');
            session()->flash('show_live_update', $this->editingStream->id);
        } else {
            session()->flash('success', 'Đã cập nhật cấu hình stream thành công.');
        }
    }

    public function confirmDelete(StreamConfiguration $stream)
    {
        if (!auth()->user()->isAdmin() && $stream->user_id !== Auth::id()) {
            abort(403);
        }
        $this->deletingStream = $stream;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        if (!auth()->user()->isAdmin() && $this->deletingStream->user_id !== Auth::id()) {
            abort(403);
        }

        // If stream is running, stop it first
        if (in_array($this->deletingStream->status, ['STREAMING', 'STARTING'])) {
            StopMultistreamJob::dispatch($this->deletingStream);
        }

        // Send cleanup command to VPS to remove downloaded files
        if ($this->deletingStream->vps_server_id) {
            CleanupStreamFilesJob::dispatch($this->deletingStream);
        }

        $this->deletingStream->delete();
        $this->showDeleteModal = false;
        session()->flash('success', 'Stream đã được xóa. Lệnh dọn dẹp file trên VPS đã được gửi đi.');
    }

    public function startStream(StreamConfiguration $stream)
    {
        \Log::info('🔍 [DEBUG] Bắt đầu hàm startStream', [
            'stream_id' => $stream->id,
            'stream_title' => $stream->title,
            'user_id' => $stream->user_id
        ]);
        
        try {
            $stream->update(['status' => 'STARTING']);
            \Log::info('🔍 [DEBUG] Đã update status thành STARTING');
            
            StartMultistreamJob::dispatch($stream);
            \Log::info('🔍 [DEBUG] Đã dispatch job StartMultistreamJob', [
                'stream_id' => $stream->id,
                'queue_connection' => config('queue.default')
            ]);
            
            session()->flash('message', "Lệnh bắt đầu stream '{$stream->title}' đã được gửi đi.");
        } catch (\Exception $e) {
            \Log::error('🔍 [DEBUG] Lỗi khi dispatch job', [
                'stream_id' => $stream->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', "Lỗi khi bắt đầu stream: {$e->getMessage()}");
        }
    }

    public function stopStream(StreamConfiguration $stream)
    {
        $stream->update(['status' => 'STOPPING']);
        StopMultistreamJob::dispatch($stream);
        session()->flash('message', "Lệnh dừng stream '{$stream->title}' đã được gửi đi.");
    }

    public function forceStopStream(StreamConfiguration $stream)
    {
        // Force update status to STOPPED for streams stuck in STOPPING
        $stream->update([
            'status' => 'STOPPED',
            'output_log' => 'Force stopped by admin',
            'last_stopped_at' => now(),
            'ffmpeg_pid' => null,
        ]);

        session()->flash('message', "Stream '{$stream->title}' đã được force stop.");
    }

    public function updateLiveStream(StreamConfiguration $stream)
    {
        if (!in_array($stream->status, ['STREAMING', 'ACTIVE'])) {
            session()->flash('error', 'Stream không đang chạy, không thể cập nhật live.');
            return;
        }

        try {
            // Send update command to VPS manager
            $vps = $stream->vpsServer;
            if (!$vps) {
                throw new \Exception('VPS not found for stream');
            }

            $response = \Illuminate\Support\Facades\Http::timeout(30)->post("http://{$vps->ip}:9999/stream/update", [
                'stream_id' => $stream->id,
                'files' => $stream->video_source_path,
                'loop' => $stream->loop,
                'playlist_order' => $stream->playlist_order ?? 'sequential'
            ]);

            if ($response->successful()) {
                session()->flash('success', "Đã cập nhật live stream '{$stream->title}' thành công!");
            } else {
                session()->flash('error', 'Không thể cập nhật live stream. VPS không phản hồi.');
            }

        } catch (\Exception $e) {
            session()->flash('error', "Lỗi cập nhật live stream: {$e->getMessage()}");
        }
    }

    public function updatingFilterUserId() { $this->resetPage(); }
    public function updatingFilterStatus() { $this->resetPage(); }

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
        $streamsQuery = StreamConfiguration::with(['user', 'vpsServer', 'userFile']);

        if ($this->filterUserId) {
            $streamsQuery->where('user_id', $this->filterUserId);
        }

        if ($this->filterStatus) {
            $streamsQuery->where('status', $this->filterStatus);
        }

        $streams = $streamsQuery->paginate(10);
        $users = User::all();
        $vpsServers = VpsServer::where('status', 'ACTIVE')->get();
        $statuses = ['PENDING', 'ACTIVE', 'INACTIVE', 'ERROR', 'STARTING', 'STOPPING', 'STREAMING', 'STOPPED'];

        // Add real-time progress to each stream
        foreach ($streams as $stream) {
            if (in_array($stream->status, ['STARTING', 'STREAMING'])) {
                $progress = $this->getStreamProgress($stream->id);
                if ($progress) {
                    $stream->progress_data = $progress;
                }
            }
        }

        // Get files for modal (create or edit)
        $userFiles = [];
        if (($this->showCreateModal || $this->showEditModal) && $this->user_id) {
            $userFiles = \App\Models\UserFile::where('user_id', $this->user_id)
                ->where('status', 'ready')
                ->get(); // Support both Google Drive and local files
        }

        return view('livewire.admin-stream-manager', [
            'streams' => $streams,
            'users' => $users,
            'vpsServers' => $vpsServers,
            'statuses' => $statuses,
            'userFiles' => $userFiles
        ])->layout('layouts.sidebar')
          ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Quản lý Streams</h1>');
    }
}
