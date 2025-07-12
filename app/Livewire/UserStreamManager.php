<?php

namespace App\Livewire;

use App\Models\StreamConfiguration;
use App\Models\UserFile;
use App\Services\VpsAllocationService;
use App\Jobs\StartStreamJob;
use App\Jobs\StopStreamJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;

class UserStreamManager extends Component
{
    use WithPagination;

    public $showCreateModal = false;
    public $showEditModal = false;
    public $showDeleteModal = false;
    public $editingStream;
    public $deletingStream;
    
    // Form fields
    public $title, $description, $user_file_ids = [], $platform = 'youtube';
    public $rtmp_url, $stream_key;
    
    // New feature fields
    public $stream_preset = 'direct'; // 'direct' or 'optimized'
    public $loop = false;
    public $scheduled_at;
    public $playlist_order = 'sequential'; // 'sequential' or 'random'

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

            'stream_preset' => 'required|in:direct,optimized',
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
            'stream_preset' => $this->stream_preset,
            'loop' => $this->loop,
            'scheduled_at' => $this->scheduled_at,
            'playlist_order' => $this->playlist_order,
            'user_file_id' => $selectedFiles->first()->id, // Primary file for backward compatibility
        ]);

        $this->showCreateModal = false;
        session()->flash('success', 'Đã tạo cấu hình stream thành công!');
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
        $this->stream_preset = $stream->stream_preset ?? 'direct';
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
            'stream_preset' => $this->stream_preset,
            'loop' => $this->loop,
            'scheduled_at' => $this->scheduled_at,
            'playlist_order' => $this->playlist_order,
            'user_file_id' => $selectedFiles->first()->id,
        ]);

        $this->showEditModal = false;
        session()->flash('success', 'Đã cập nhật cấu hình stream thành công.');
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
        $this->deletingStream->delete();
        $this->showDeleteModal = false;
        session()->flash('success', 'Stream configuration deleted successfully.');
    }

    public function startStream(StreamConfiguration $stream)
    {
        if ($stream->user_id !== Auth::id()) {
            abort(403);
        }
        $stream->update(['status' => 'STARTING']);
        StartStreamJob::dispatch($stream);
        session()->flash('message', 'Lệnh bắt đầu stream đã được gửi đi.');
    }

    public function stopStream(StreamConfiguration $stream)
    {
        if ($stream->user_id !== Auth::id()) {
            abort(403);
        }
        $stream->update(['status' => 'STOPPING']);
        StopStreamJob::dispatch($stream);
        session()->flash('message', 'Lệnh dừng stream đã được gửi đi.');
    }

    public function closeModal()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->showDeleteModal = false;
        $this->reset();
    }

    public function render()
    {
        $streams = Auth::user()->streamConfigurations()->with('vpsServer')->paginate(10);
        $userFiles = Auth::user()->files()->where('status', 'ready')->get();

        return view('livewire.user-stream-manager', [
            'streams' => $streams,
            'userFiles' => $userFiles,
            'platforms' => $this->getPlatforms(),
        ])->layout('layouts.sidebar')
          ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Quản lý Stream</h1>');
    }
}
