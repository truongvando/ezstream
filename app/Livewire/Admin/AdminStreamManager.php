<?php

namespace App\Livewire\Admin;

use App\Models\StreamConfiguration;
use App\Models\User;
use App\Models\VpsServer;
use App\Jobs\StartStreamJob;
use App\Jobs\StopStreamJob;
use App\Services\LocalStreamingService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\VpsAllocationService;
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
    public $user_id, $title, $description, $vps_server_id, $video_source_path, $rtmp_url, $stream_key, $ffmpeg_options;
    public $platform = 'youtube';
    public $user_file_id;
    public $stream_preset = 'optimized';
    public $loop = false;
    
    // Scheduling properties
    public $scheduled_stream = false;
    public $scheduled_at;
    public $scheduled_end;

    // Filters
    public $filterUserId = '';
    public $filterStatus = '';
    
    // Local streaming
    public $enableLocalStreaming = false;
    public $localStreamPid = null;
    public $localStreamStatus = '';
    protected $localStreamingService;

    protected function rules()
    {
        return [
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'vps_server_id' => 'required|exists:vps_servers,id',
            'video_source_path' => 'required|string|max:255',
            'rtmp_url' => 'required|url',
            'stream_key' => 'required|string',
            'ffmpeg_options' => 'nullable|string',
        ];
    }
    
    public function mount()
    {
        // Check if local FFmpeg is available for admin
        if (auth()->user()->isAdmin()) {
            $this->localStreamingService = app(LocalStreamingService::class);
            $ffmpegCheck = $this->localStreamingService->testFFmpegInstallation();
            $this->enableLocalStreaming = $ffmpegCheck['success'];
        }
        
        // Check if testing specific VPS
        if (request()->has('test_vps')) {
            $testVpsId = request()->get('test_vps');
            $testVps = VpsServer::find($testVpsId);
            if ($testVps && $testVps->status === 'ACTIVE') {
                $this->vps_server_id = $testVpsId;
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
        $this->reset(['title', 'description', 'user_file_id', 'platform', 'rtmp_url', 'stream_key', 'loop', 'scheduled_at', 'scheduled_end', 'stream_preset']);
        $this->user_id = auth()->id(); // Default to admin
        $this->platform = 'youtube';
        $this->stream_preset = 'direct';
        $this->showCreateModal = true;
    }
    
    /**
     * Store new stream
     */
    public function store()
    {
        $this->validate([
            'user_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_file_id' => 'required|exists:user_files,id',
            'platform' => 'required|in:youtube,facebook,twitch,custom',
            'rtmp_url' => 'required|url',
            'stream_key' => 'required|string',
            'stream_preset' => 'required|in:direct,optimized,high_quality,low_latency',
            'loop' => 'boolean'
        ]);
        
        // Get user file
        $userFile = \App\Models\UserFile::find($this->user_file_id);
        
        // Create stream configuration
        $stream = StreamConfiguration::create([
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'vps_server_id' => $this->enableLocalStreaming ? null : ($this->vps_server_id ?? VpsServer::where('status', 'ACTIVE')->first()->id),
            'video_source_path' => $userFile->path ?? 'google_drive',
            'rtmp_url' => $this->rtmp_url,
            'stream_key' => $this->stream_key,
            'status' => 'INACTIVE',
            'stream_preset' => $this->stream_preset,
            'loop' => $this->loop,
            'user_file_id' => $this->user_file_id
        ]);
        
        $this->showCreateModal = false;
        session()->flash('success', 'Stream configuration created successfully.');
        
        // Auto start if local streaming enabled
        if ($this->enableLocalStreaming) {
            $this->startStream($stream);
        }
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
        $this->user_file_id = $stream->user_file_id;
        $this->platform = $this->detectPlatformFromUrl($stream->rtmp_url);
        $this->rtmp_url = $stream->rtmp_url;
        $this->stream_key = $stream->stream_key;
        $this->stream_preset = $stream->stream_preset ?? 'optimized';
        $this->loop = $stream->loop ?? false;
        $this->scheduled_at = $stream->scheduled_at ? $stream->scheduled_at->format('Y-m-d\TH:i') : null;
        $this->scheduled_end = $stream->scheduled_end ? $stream->scheduled_end->format('Y-m-d\TH:i') : null;

        $this->dispatch('open-modal', name: 'edit-stream-modal');
    }

    public function update()
    {
        if ($this->editingStream->user_id !== Auth::id()) {
            abort(403);
        }
        $this->validate();
        
        $userFile = \App\Models\UserFile::find($this->user_file_id);
        
        $this->editingStream->update([
            'title' => $this->title,
            'description' => $this->description,
            'video_source_path' => $userFile->path ?? 'google_drive',
            'rtmp_url' => $this->rtmp_url,
            'stream_key' => $this->stream_key,
            'stream_preset' => $this->stream_preset,
            'loop' => $this->loop,
            'scheduled_at' => $this->scheduled_at,
            'scheduled_end' => $this->scheduled_end,
            'user_file_id' => $userFile->id,
        ]);

        $this->dispatch('close-modal', name: 'edit-stream-modal');
        session()->flash('success', 'Đã cập nhật cấu hình stream thành công.');
    }

    public function confirmDelete(StreamConfiguration $stream)
    {
        if ($stream->user_id !== Auth::id()) {
            abort(403);
        }
        $this->deletingStream = $stream;
        $this->dispatch('open-modal', name: 'delete-stream-modal');
    }

    public function delete()
    {
        if ($this->deletingStream->user_id !== Auth::id()) {
            abort(403);
        }
        $this->deletingStream->delete();
        $this->dispatch('close-modal', name: 'delete-stream-modal');
        session()->flash('success', 'Stream configuration deleted successfully.');
    }

    public function startStream(StreamConfiguration $stream)
    {
        // Check if admin wants to use local streaming
        if ($this->enableLocalStreaming && auth()->user()->isAdmin()) {
            $this->startLocalStream($stream);
        } else {
            // Traditional VPS streaming
            $stream->update(['status' => 'STARTING']);
            StartStreamJob::dispatch($stream);
            session()->flash('message', "Lệnh bắt đầu stream '{$stream->title}' đã được gửi đi.");
        }
    }
    
    /**
     * Start streaming locally from server (admin only)
     */
    public function startLocalStream(StreamConfiguration $stream)
    {
        try {
            $this->localStreamingService = app(LocalStreamingService::class);
            
            // Determine input source
            $options = [
                'output_url' => $stream->rtmp_url . '/' . $stream->stream_key,
                'preset' => $stream->stream_preset ?? 'optimized'
            ];
            
            // Check if it's a Google Drive file
            if ($stream->userFile && $stream->userFile->google_drive_file_id) {
                // Stream from Google Drive
                $result = $this->localStreamingService->testGoogleDriveStream(
                    $stream->userFile->google_drive_file_id,
                    $options
                );
            } else {
                // Stream from local file
                $options['input_file'] = storage_path('app/' . $stream->video_source_path);
                $result = $this->localStreamingService->testLocalStream($options);
            }
            
            if ($result['success']) {
                $this->localStreamPid = $result['pid'];
                $stream->update([
                    'status' => 'STREAMING',
                    'ffmpeg_pid' => $result['pid'],
                    'output_log' => 'Local streaming from server'
                ]);
                
                session()->flash('success', "Stream '{$stream->title}' đã bắt đầu từ server local!");
                Log::info('Admin started local stream', [
                    'stream_id' => $stream->id,
                    'pid' => $result['pid'],
                    'command' => $result['command'] ?? 'N/A'
                ]);
            } else {
                session()->flash('error', 'Không thể bắt đầu stream: ' . $result['error']);
            }
            
        } catch (\Exception $e) {
            Log::error('Local streaming error', ['error' => $e->getMessage()]);
            session()->flash('error', 'Lỗi khi bắt đầu stream: ' . $e->getMessage());
        }
    }

    public function stopStream(StreamConfiguration $stream)
    {
        // Check if it's a local stream
        if ($stream->output_log === 'Local streaming from server' && $stream->ffmpeg_pid) {
            $this->stopLocalStream($stream);
        } else {
            // Traditional VPS streaming
            $stream->update(['status' => 'STOPPING']);
            StopStreamJob::dispatch($stream);
            session()->flash('message', "Lệnh dừng stream '{$stream->title}' đã được gửi đi.");
        }
    }
    
    /**
     * Stop local stream
     */
    public function stopLocalStream(StreamConfiguration $stream)
    {
        try {
            $this->localStreamingService = app(LocalStreamingService::class);
            $result = $this->localStreamingService->stopLocalStream($stream->ffmpeg_pid);
            
            if ($result['success']) {
                $stream->update([
                    'status' => 'STOPPED',
                    'ffmpeg_pid' => null
                ]);
                
                session()->flash('success', "Stream '{$stream->title}' đã dừng!");
                Log::info('Admin stopped local stream', [
                    'stream_id' => $stream->id,
                    'pid' => $stream->ffmpeg_pid
                ]);
            } else {
                session()->flash('error', 'Không thể dừng stream: ' . $result['error']);
            }
            
        } catch (\Exception $e) {
            Log::error('Error stopping local stream', ['error' => $e->getMessage()]);
            session()->flash('error', 'Lỗi khi dừng stream: ' . $e->getMessage());
        }
    }
    
    /**
     * Toggle local streaming mode
     */
    public function toggleLocalStreaming()
    {
        if (auth()->user()->isAdmin()) {
            $this->enableLocalStreaming = !$this->enableLocalStreaming;
            
            if ($this->enableLocalStreaming) {
                session()->flash('info', 'Chế độ Local Streaming đã bật - Stream trực tiếp từ server!');
            } else {
                session()->flash('info', 'Chế độ VPS Streaming đã bật - Stream qua VPS!');
            }
        }
    }
    
    /**
     * Set quick duration for scheduling
     */
    public function setQuickDuration($hours)
    {
        if ($hours === 'now') {
            $this->scheduled_at = now()->format('Y-m-d\TH:i');
            $this->scheduled_end = null;
        } elseif ($hours === 0) {
            // Infinite duration - set start time to now if empty
            if (!$this->scheduled_at) {
                $this->scheduled_at = now()->format('Y-m-d\TH:i');
            }
            $this->scheduled_end = null;
        } else {
            // Set start time to now if empty
            if (!$this->scheduled_at) {
                $this->scheduled_at = now()->format('Y-m-d\TH:i');
            }
            // Calculate end time
            $startTime = \Carbon\Carbon::parse($this->scheduled_at);
            $this->scheduled_end = $startTime->addHours($hours)->format('Y-m-d\TH:i');
        }
    }
    
    /**
     * Set schedule presets
     */
    public function setSchedulePreset($preset)
    {
        $tomorrow = now()->addDay();
        
        switch ($preset) {
            case 'morning':
                $this->scheduled_at = $tomorrow->setTime(7, 0)->format('Y-m-d\TH:i');
                break;
            case 'afternoon':
                $this->scheduled_at = $tomorrow->setTime(14, 0)->format('Y-m-d\TH:i');
                break;
            case 'evening':
                $this->scheduled_at = $tomorrow->setTime(19, 0)->format('Y-m-d\TH:i');
                break;
            case 'night':
                $this->scheduled_at = $tomorrow->setTime(22, 0)->format('Y-m-d\TH:i');
                break;
        }
        
        // Clear end time for presets
        $this->scheduled_end = null;
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
        
        // Get files for create modal
        $userFiles = [];
        if ($this->showCreateModal && $this->user_id) {
            $userFiles = \App\Models\UserFile::where('user_id', $this->user_id)
                ->where('status', 'AVAILABLE')
                ->where('disk', 'google_drive')
                ->get();
        }

        return view('livewire.admin-stream-manager', [
            'streams' => $streams,
            'users' => $users,
            'vpsServers' => $vpsServers,
            'statuses' => $statuses,
            'userFiles' => $userFiles
        ])->layout('layouts.admin');
    }
}
