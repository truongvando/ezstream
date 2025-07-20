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

/**
 * Base Stream Manager - Chứa logic chung cho Admin và User Stream Manager
 * Giải quyết vấn đề trùng lặp code và thống nhất kiến trúc
 */
abstract class BaseStreamManager extends Component
{
    use WithPagination;

    protected $listeners = [
        'refreshComponent' => '$refresh',
        'refreshStreams' => '$refresh'
    ];

    // Modals
    public $showCreateModal = false;
    public $showEditModal = false;
    public $showDeleteModal = false;
    public $showQuickStreamModal = false;
    
    // Models
    public $editingStream;
    public $deletingStream;

    // Filter properties
    public $filterStatus = '';
    public $filterUserId = ''; // Only used by admin

    // Pagination
    public $streamsPerPage = 9;

    // Form fields
    public $title, $description, $user_file_ids = [], $platform = 'youtube';
    public $rtmp_url, $stream_key;
    public $userFiles = []; // Holds the list of files for the modals

    // Advanced fields
    public $loop = false;
    public $enable_schedule = false;
    public $scheduled_at;
    public $scheduled_end;
    public $playlist_order = 'sequential';
    public $keep_files_on_agent = false;

    // Quick Stream fields
    public $quickTitle, $quickDescription, $quickPlatform = 'youtube';
    public $quickRtmpUrl, $quickStreamKey;
    public $quickLoop = false;
    public $quickPlaylistOrder = 'sequential';
    public $quickEnableSchedule = false;
    public $quickScheduledAt, $quickScheduledEnd;
    public $quickSelectedFiles = [];
    public $video_source_id; // For quick upload

    // Abstract properties - must be defined in child classes
    abstract protected function canManageAllStreams(): bool;
    abstract protected function canForceStop(): bool;
    abstract protected function requiresSubscription(): bool;
    abstract protected function getLayoutName(): string;

    public function mount()
    {
        $this->cleanupHangingStreams();
        $this->loadUserFiles();
    }

    /**
     * Enhanced refresh method with status sync
     */
    public function refreshStreams()
    {
        // Auto-fix hanging streams on each refresh
        $this->cleanupHangingStreams();

        // Force component re-render
        $this->render();
    }

    /**
     * Enhanced cleanup for streams stuck in various states
     */
    private function cleanupHangingStreams()
    {
        try {
            $fixedCount = 0;

            // Fix streams stuck in STOPPING (5 minutes)
            $fixedCount += $this->fixStuckStreams('STOPPING', 5, 'INACTIVE', 'Auto-fixed: stuck in STOPPING');

            // Fix streams stuck in STARTING for too long (8 minutes - more aggressive)
            $fixedCount += $this->fixStuckStreams('STARTING', 8, 'ERROR', 'Auto-fixed: stuck in STARTING for too long');

            if ($fixedCount > 0) {
                Log::info("🔧 [BaseStreamManager] Auto-fixed {$fixedCount} hanging streams");
                session()->flash('message', "Đã tự động sửa {$fixedCount} stream bị treo.");
            }

        } catch (\Exception $e) {
            Log::error("❌ [BaseStreamManager] Failed to cleanup hanging streams: {$e->getMessage()}");
        }
    }

    /**
     * Fix streams stuck in a specific status
     */
    private function fixStuckStreams(string $status, int $minutesThreshold, string $newStatus, string $errorMessage): int
    {
        $query = StreamConfiguration::where('status', $status);

        if ($status === 'STARTING') {
            $query->where('last_started_at', '<', now()->subMinutes($minutesThreshold));
        } else {
            $query->where('updated_at', '<', now()->subMinutes($minutesThreshold));
        }

        if (!$this->canManageAllStreams()) {
            $query->where('user_id', Auth::id());
        }

        $stuckStreams = $query->get();
        $fixedCount = 0;

        foreach ($stuckStreams as $stream) {
            $timeField = $status === 'STARTING' ? 'last_started_at' : 'updated_at';
            $stuckDuration = now()->diffInMinutes($stream->$timeField);

            Log::warning("🔧 [BaseStreamManager] Auto-fixing stream #{$stream->id} stuck in {$status} for {$stuckDuration} minutes");

            $updateData = [
                'status' => $newStatus,
                'vps_server_id' => null,
                'error_message' => "{$errorMessage} ({$stuckDuration} minutes)",
            ];

            if ($newStatus === 'INACTIVE') {
                $updateData['last_stopped_at'] = now();
            }

            $stream->update($updateData);

            // Decrement VPS stream count if needed
            if ($stream->vps_server_id && $stream->vpsServer) {
                $stream->vpsServer->decrement('current_streams');
            }

            $fixedCount++;
        }

        return $fixedCount;
    }

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
        Log::info('BaseStreamManager::create() method called');
        
        $user = Auth::user();
        
        // Check subscription requirement
        if ($this->requiresSubscription() && !$user->isAdmin()) {
            $activeSubscription = $user->subscriptions()->where('status', 'ACTIVE')->first();
            if (!$activeSubscription) {
                session()->flash('error', 'Bạn cần có gói dịch vụ đang hoạt động để tạo stream. Vui lòng mua gói dịch vụ trước.');
                return redirect()->route('billing.manager');
            }
        }
        
        // Reset all modal states first
        $this->reset(['showCreateModal', 'showEditModal', 'showQuickStreamModal']);
        $this->resetValidation();

        // Reset form fields
        $this->reset(['title', 'description', 'user_file_ids', 'platform', 'rtmp_url', 'stream_key', 'playlist_order', 'loop', 'enable_schedule', 'scheduled_at', 'scheduled_end', 'keep_files_on_agent']);

        // Set defaults
        $this->user_file_ids = [];
        $this->platform = 'youtube';
        $this->playlist_order = 'sequential';
        $this->loop = false;
        $this->enable_schedule = false;
        $this->keep_files_on_agent = false;
        $this->editingStream = null;

        // Load user files
        $this->loadUserFiles();

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
            'custom' => ''
        ];

        return $backupPlatforms[$platform] ?? '';
    }

    public function getPlatforms()
    {
        return [
            'youtube' => '📺 YouTube Live',
            'custom' => '⚙️ Custom RTMP'
        ];
    }

    public function closeModal()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->showQuickStreamModal = false;
        $this->resetValidation();
    }

    /**
     * Get streams query with proper permissions
     */
    protected function getStreamsQuery()
    {
        $query = StreamConfiguration::with(['user', 'vpsServer', 'userFile']);

        // Apply user filter if not admin
        if (!$this->canManageAllStreams()) {
            $query->where('user_id', Auth::id());
        }

        // Apply admin filters
        if ($this->canManageAllStreams()) {
            if ($this->filterUserId) {
                $query->where('user_id', $this->filterUserId);
            }
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return $query;
    }

    /**
     * Load user files for display in modals
     */
    protected function loadUserFiles()
    {
        $this->userFiles = $this->getUserFiles();
    }

    /**
     * Get user files with proper permissions
     */
    protected function getUserFiles()
    {
        $userId = $this->canManageAllStreams() && isset($this->user_id) ? $this->user_id : Auth::id();

        return UserFile::where('user_id', $userId)
            ->where('status', 'ready')
            ->latest()
            ->get();
    }

    /**
     * Open Quick Stream Modal
     */
    public function openQuickStreamModal()
    {
        Log::info('🚀 Opening Quick Stream Modal');
        
        $user = Auth::user();
        
        // Check subscription requirement
        if ($this->requiresSubscription() && !$user->isAdmin()) {
            $activeSubscription = $user->subscriptions()->where('status', 'ACTIVE')->first();
            if (!$activeSubscription) {
                session()->flash('error', 'Bạn cần có gói dịch vụ đang hoạt động để tạo stream. Vui lòng mua gói dịch vụ trước.');
                return redirect()->route('billing.manager');
            }
        }

        // Reset quick stream fields
        $this->reset([
            'quickTitle', 'quickDescription', 'quickPlatform', 'quickRtmpUrl', 'quickStreamKey',
            'quickLoop', 'quickPlaylistOrder', 'quickEnableSchedule', 'quickScheduledAt', 'quickScheduledEnd',
            'quickSelectedFiles', 'video_source_id'
        ]);
        
        $this->quickPlatform = 'youtube';
        $this->quickPlaylistOrder = 'sequential';
        $this->quickSelectedFiles = [];
        
        $this->showQuickStreamModal = true;
        
        Log::info('✅ Quick Stream Modal opened successfully');
    }

    /**
     * Get stream progress for real-time updates
     */
    protected function getStreamProgress($streamId)
    {
        try {
            $progressService = app(StreamProgressService::class);
            return $progressService->getProgress($streamId);
        } catch (\Exception $e) {
            Log::error("Failed to get stream progress for stream {$streamId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Start a stream
     */
    public function startStream($stream)
    {
        // Handle both StreamConfiguration object and ID
        if (is_numeric($stream)) {
            $streamId = $stream;
            $stream = StreamConfiguration::find($streamId);
        }

        if (!$stream) {
            session()->flash('error', 'Stream không tồn tại.');
            return;
        }

        // Check permissions
        if (!$this->canManageAllStreams() && $stream->user_id !== Auth::id()) {
            abort(403);
        }

        if ($stream->status === 'STREAMING') {
            session()->flash('error', 'Stream đang chạy.');
            return;
        }

        if ($stream->status === 'STARTING') {
            session()->flash('error', 'Stream đang được khởi động.');
            return;
        }

        $stream->update(['status' => 'STARTING']);
        StartMultistreamJob::dispatch($stream);
        session()->flash('message', 'Lệnh bắt đầu stream đã được gửi đi.');
    }

    /**
     * Stop a stream
     */
    public function stopStream(StreamConfiguration $stream)
    {
        // Check permissions
        if (!$this->canManageAllStreams() && $stream->user_id !== Auth::id()) {
            abort(403);
        }

        $stream->update(['status' => 'STOPPING']);
        StopMultistreamJob::dispatch($stream);

        // Force refresh UI immediately
        $this->dispatch('refreshStreams');

        session()->flash('message', 'Lệnh dừng stream đã được gửi đi.');
    }

    /**
     * Update a live stream (force refresh playlist)
     */
    public function updateLiveStream($streamId)
    {
        $stream = StreamConfiguration::find($streamId);

        if (!$stream) {
            session()->flash('error', 'Stream không tồn tại.');
            return;
        }

        // Check permissions
        if (!$this->canManageAllStreams() && $stream->user_id !== Auth::id()) {
            abort(403);
        }

        // Only allow update for running streams
        if (!in_array($stream->status, ['STREAMING', 'STARTING'])) {
            session()->flash('error', 'Chỉ có thể cập nhật stream đang chạy.');
            return;
        }

        // Dispatch update job
        \App\Jobs\UpdateMultistreamJob::dispatch($stream);

        session()->flash('success', 'Đã gửi lệnh cập nhật stream! Playlist sẽ được làm mới trong vài giây.');

        // Force refresh UI
        $this->dispatch('refreshStreams');
    }

    /**
     * Confirm delete stream
     */
    public function confirmDelete(StreamConfiguration $stream)
    {
        Log::info('BaseStreamManager confirmDelete called', [
            'stream_id' => $stream->id,
            'user_id' => Auth::id(),
            'can_manage_all' => $this->canManageAllStreams(),
            'stream_user_id' => $stream->user_id
        ]);

        // Check permissions
        if (!$this->canManageAllStreams() && $stream->user_id !== Auth::id()) {
            Log::error('Permission denied for confirmDelete', [
                'stream_id' => $stream->id,
                'user_id' => Auth::id(),
                'stream_user_id' => $stream->user_id
            ]);
            abort(403);
        }

        $this->deletingStream = $stream;
        $this->showDeleteModal = true;

        Log::info('Delete modal opened', ['stream_id' => $stream->id]);
    }

    /**
     * Delete a stream
     */
    public function delete()
    {
        if (!$this->deletingStream) {
            return;
        }

        // Check permissions
        if (!$this->canManageAllStreams() && $this->deletingStream->user_id !== Auth::id()) {
            abort(403);
        }

        // Stop stream if running
        if (in_array($this->deletingStream->status, ['STREAMING', 'STARTING'])) {
            $this->deletingStream->update(['status' => 'STOPPING']);
            StopMultistreamJob::dispatch($this->deletingStream);
        }

        $this->deletingStream->delete();
        $this->showDeleteModal = false;
        session()->flash('success', 'Stream đã được xóa. Tất cả file liên quan cũng đã được dọn dẹp.');
    }

    /**
     * Update quick platform URL when platform changes
     */
    public function updateQuickPlatformUrl()
    {
        $this->quickRtmpUrl = $this->getPlatformUrl($this->quickPlatform);
    }

    /**
     * Reset quick stream form
     */
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
    }
}
