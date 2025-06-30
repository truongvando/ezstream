<?php

namespace App\Livewire\Admin;

use App\Models\StreamConfiguration;
use App\Models\User;
use App\Models\VpsServer;
use App\Jobs\StartStreamJob;
use App\Jobs\StopStreamJob;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\UserFile;

class AdminStreamManager extends Component
{
    use WithPagination;

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
    public $stream_preset = 'direct';
    public $loop = false;
    public $enable_schedule = false;
    public $playlist_order = 'sequential';
    
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
            'stream_preset' => 'required|in:direct,optimized',
            'loop' => 'boolean',
            'scheduled_at' => 'nullable|date',
            'playlist_order' => 'required|in:sequential,random',
        ];
    }
    
    public function mount()
    {
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
            'video_source_path' => json_encode($fileList), // Store file list as JSON
            'rtmp_url' => $rtmpUrl,
            'rtmp_backup_url' => $backupRtmpUrl, // Auto-generated backup
            'stream_key' => $this->stream_key,
            'status' => 'INACTIVE',
            'stream_preset' => $this->stream_preset,
            'loop' => $this->loop,
            'scheduled_at' => $this->enable_schedule ? $this->scheduled_at : null,
            'scheduled_end' => $this->enable_schedule ? $this->scheduled_end : null,
            'playlist_order' => $this->playlist_order,
            'user_file_id' => $selectedFiles->first()->id // Primary file for backward compatibility
        ]);
        
        $this->showCreateModal = false;
        session()->flash('success', 'Stream đã được tạo thành công.');
        
        // Auto start if scheduling is NOT enabled
        if (!$this->enable_schedule) {
            $this->startStream($stream);
        }
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
        $this->stream_preset = $stream->stream_preset ?? 'optimized';
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
            'video_source_path' => json_encode($fileList), // Store file list as JSON
            'rtmp_url' => $rtmpUrl,
            'rtmp_backup_url' => $backupRtmpUrl, // Auto-generated backup
            'stream_key' => $this->stream_key,
            'stream_preset' => $this->stream_preset,
            'loop' => $this->loop,
            'scheduled_at' => $this->enable_schedule ? $this->scheduled_at : null,
            'scheduled_end' => $this->enable_schedule ? $this->scheduled_end : null,
            'playlist_order' => $this->playlist_order,
            'user_file_id' => $selectedFiles->first()->id // Primary file for backward compatibility
        ]);

        $this->showEditModal = false;
        session()->flash('success', 'Đã cập nhật cấu hình stream thành công.');
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
        $this->deletingStream->delete();
        $this->showDeleteModal = false;
        session()->flash('success', 'Stream configuration deleted successfully.');
    }

    public function startStream(StreamConfiguration $stream)
    {
        $stream->update(['status' => 'STARTING']);
        StartStreamJob::dispatch($stream);
        session()->flash('message', "Lệnh bắt đầu stream '{$stream->title}' đã được gửi đi.");
    }

    public function stopStream(StreamConfiguration $stream)
    {
        $stream->update(['status' => 'STOPPING']);
        StopStreamJob::dispatch($stream);
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
            'facebook' => 'rtmp://live-api-s.facebook.com/rtmp',
            'twitch' => 'rtmp://live.twitch.tv/app',
            'instagram' => 'rtmp://live-upload.instagram.com/rtmp',
            'tiktok' => 'rtmp://push.tiktokcdn.com/live',
            'custom' => ''
        ];
        return $platforms[$platform] ?? '';
    }

    protected function getPlatformBackupUrl($platform)
    {
        $backupPlatforms = [
            'youtube' => 'rtmp://b.rtmp.youtube.com/live2',
            'facebook' => 'rtmp://live-api-s.facebook.com/rtmp', // Facebook usually same server
            'twitch' => 'rtmp://live-jfk.twitch.tv/app', // Different region
            'instagram' => 'rtmp://live-upload.instagram.com/rtmp', // Same for Instagram
            'tiktok' => 'rtmp://push.tiktokcdn-sg.com/live', // Singapore server
            'custom' => '' // No backup for custom
        ];

        return $backupPlatforms[$platform] ?? '';
    }

    protected function detectPlatformFromUrl($url)
    {
        if (str_contains($url, 'youtube.com')) return 'youtube';
        if (str_contains($url, 'facebook.com')) return 'facebook';
        if (str_contains($url, 'twitch.tv')) return 'twitch';
        if (str_contains($url, 'instagram.com')) return 'instagram';
        if (str_contains($url, 'tiktok')) return 'tiktok';
        return 'custom';
    }

    public function getPlatforms()
    {
        return [
            'youtube' => '📺 YouTube Live',
            'facebook' => '📘 Facebook Live', 
            'twitch' => '🎮 Twitch',
            'instagram' => '📷 Instagram Live',
            'tiktok' => '🎵 TikTok Live',
            'custom' => '⚙️ Custom RTMP'
        ];
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
        
        // Get files for modal (create or edit)
        $userFiles = [];
        if (($this->showCreateModal || $this->showEditModal) && $this->user_id) {
            $userFiles = \App\Models\UserFile::where('user_id', $this->user_id)
                ->where('status', 'AVAILABLE')
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
