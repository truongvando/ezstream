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

    protected $listeners = ['refreshComponent' => '$refresh'];

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

    // Form fields
    public $title, $description, $user_file_ids = [], $platform = 'youtube';
    public $rtmp_url, $stream_key;

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
    }

    /**
     * Cleanup streams that are stuck in STOPPING status
     */
    private function cleanupHangingStreams()
    {
        try {
            $timeout = 300; // 5 minutes
            $query = StreamConfiguration::where('status', 'STOPPING')
                ->where('updated_at', '<', now()->subSeconds($timeout));

            // Apply user filter if not admin
            if (!$this->canManageAllStreams()) {
                $query->where('user_id', Auth::id());
            }

            $hangingStreams = $query->get();

            foreach ($hangingStreams as $stream) {
                $stuckDuration = now()->diffInSeconds($stream->updated_at);

                Log::warning("🔧 [BaseStreamManager] Auto-fixing hanging stream #{$stream->id} for user #{$stream->user_id} stuck for {$stuckDuration}s");

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
            Log::error("❌ [BaseStreamManager] Failed to cleanup hanging streams: {$e->getMessage()}");
        }
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
        session()->flash('message', 'Lệnh dừng stream đã được gửi đi.');
    }

    /**
     * Confirm delete stream
     */
    public function confirmDelete(StreamConfiguration $stream)
    {
        // Check permissions
        if (!$this->canManageAllStreams() && $stream->user_id !== Auth::id()) {
            abort(403);
        }

        $this->deletingStream = $stream;
        $this->showDeleteModal = true;
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
