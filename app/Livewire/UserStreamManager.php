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
    public $title, $description, $user_file_id, $platform;
    public $rtmp_url, $stream_key;
    
    // New feature fields
    public $stream_preset = 'direct'; // 'direct' or 'optimized'
    public $loop = false;
    public $scheduled_at;

    protected function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_file_id' => 'required|exists:user_files,id',
            'platform' => 'required|string',
            'stream_key' => 'required|string',
            'rtmp_url' => 'required_if:platform,custom|nullable|url',
            'stream_preset' => 'required|in:direct,optimized',
            'loop' => 'boolean',
            'scheduled_at' => 'nullable|date',
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
        
        $this->reset(['title', 'description', 'user_file_id', 'platform', 'rtmp_url', 'stream_key']);
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
            'facebook' => 'rtmp://live-api-s.facebook.com/rtmp',
            'twitch' => 'rtmp://live.twitch.tv/app',
            'instagram' => 'rtmp://live-upload.instagram.com/rtmp',
            'tiktok' => 'rtmp://push.tiktokcdn.com/live',
            'custom' => ''
        ];

        return $platforms[$platform] ?? '';
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

    public function store()
    {
        $this->validate();

        $vpsAllocationService = new VpsAllocationService();
        $optimalVps = $vpsAllocationService->findOptimalVps();

        if (!$optimalVps) {
            session()->flash('error', 'Hệ thống đang quá tải. Vui lòng thử lại sau.');
            return;
        }
        
        $userFile = UserFile::find($this->user_file_id);
        $rtmpUrl = $this->platform === 'custom' ? $this->rtmp_url : $this->getPlatformUrl($this->platform);

        Auth::user()->streamConfigurations()->create([
            'title' => $this->title,
            'description' => $this->description,
            'vps_server_id' => $optimalVps->id,
            'video_source_path' => $userFile->disk === 'google_drive' ? $userFile->google_drive_file_id : $userFile->path,
            'rtmp_url' => $rtmpUrl,
            'stream_key' => $this->stream_key,
            'stream_preset' => $this->stream_preset,
            'loop' => $this->loop,
            'scheduled_at' => $this->scheduled_at,
            'ffmpeg_options' => '', // Deprecated in favor of presets
            'user_file_id' => $userFile->id, // Để biết source là Google Drive hay local
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
        
        // Find the UserFile that corresponds to the video source path
        $userFile = UserFile::where('path', $stream->video_source_path)
                            ->where('user_id', Auth::id())
                            ->first();

        $this->user_file_id = $userFile ? $userFile->id : null;
        
        // Detect platform from RTMP URL
        $this->platform = $this->detectPlatformFromUrl($stream->rtmp_url);
        $this->rtmp_url = $stream->rtmp_url;
        $this->stream_key = $stream->stream_key;
        
        // Load new feature fields
        $this->stream_preset = $stream->stream_preset ?? 'direct';
        $this->loop = $stream->loop ?? false;
        $this->scheduled_at = $stream->scheduled_at ? $stream->scheduled_at->format('Y-m-d\TH:i') : null;

        $this->showEditModal = true;
    }

    public function update()
    {
        if ($this->editingStream->user_id !== Auth::id()) {
            abort(403);
        }
        $this->validate();
        
        $userFile = UserFile::find($this->user_file_id);
        $rtmpUrl = $this->platform === 'custom' ? $this->rtmp_url : $this->getPlatformUrl($this->platform);

        $this->editingStream->update([
            'title' => $this->title,
            'description' => $this->description,
            'video_source_path' => $userFile->disk === 'google_drive' ? $userFile->google_drive_file_id : $userFile->path,
            'rtmp_url' => $rtmpUrl,
            'stream_key' => $this->stream_key,
            'stream_preset' => $this->stream_preset,
            'loop' => $this->loop,
            'scheduled_at' => $this->scheduled_at,
            'ffmpeg_options' => '', // Deprecated
            'user_file_id' => $userFile->id,
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
        $userFiles = Auth::user()->files()->where('status', 'AVAILABLE')->get();

        return view('livewire.user-stream-manager', [
            'streams' => $streams,
            'userFiles' => $userFiles,
            'platforms' => $this->getPlatforms(),
        ])->layout('layouts.sidebar')
          ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Quản lý Stream</h1>');
    }
}
