<?php

namespace App\Livewire;

use App\Models\UserFile;
use App\Models\StreamConfiguration;
use App\Models\VpsServer;
use App\Services\Stream\StreamManager as StreamManagerService;
use App\Jobs\StopMultistreamJob;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class StreamManager extends Component
{
    use WithPagination;

    // Properties for creating/editing streams
    public $streamId;
    public $stream_name, $platform = 'youtube', $rtmp_url, $stream_key, $video_source;
    public $video_source_id, $user_id, $status = 'pending', $description;
    public $is_loop = false, $playlist_order = 'sequential', $is_scheduled = false, $scheduled_at, $scheduled_end;

    // Properties for UI control
    public $showCreateModal = false;
    public $showDeleteModal = false;
    public $showLogsModal = false;

    // Properties for Quick Stream Modal
    public $showQuickStreamModal = false;
    public $quickStreamName, $quickPlatform = 'youtube', $quickStreamKey, $quickVideoSource = 'upload';
    
    // Other properties
    public $selectedFile, $selectedFileDetails, $searchTerm = '';
    public $streamLogs = '';

    protected $listeners = ['refreshStreams' => '$refresh'];

    protected function rules()
    {
        return [
            'stream_name' => 'required|string|max:255',
            'platform' => 'required|string|in:youtube,custom',
            'rtmp_url' => 'required_if:platform,custom|nullable|url',
            'stream_key' => 'required|string',
            'video_source_id' => 'required|exists:user_files,id',
            'user_id' => 'required|exists:users,id',
            'description' => 'nullable|string|max:1000',
            'is_loop' => 'boolean',
            'playlist_order' => 'required|string|in:sequential,random',
            'is_scheduled' => 'boolean',
            'scheduled_at' => 'required_if:is_scheduled,true|nullable|date',
            'scheduled_end' => 'nullable|date|after_or_equal:scheduled_at',
        ];
    }
    
    public function render()
    {
        $query = StreamConfiguration::with('user', 'vpsServer', 'files');

        if (!Auth::user()->isAdmin()) {
            $query->where('user_id', Auth::id());
        }

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->searchTerm . '%')
                  ->orWhereHas('user', function ($userQuery) {
                      $userQuery->where('name', 'like', '%' . $this->searchTerm . '%');
                  });
            });
        }

        $streams = $query->latest()->paginate(10);
        $userFiles = UserFile::where('user_id', Auth::id())->where('is_locked', false)->get();
        $platforms = ['youtube' => 'YouTube', 'custom' => 'Custom'];
        
        return view('livewire.stream-manager', [
            'streams' => $streams,
            'userFiles' => $userFiles,
            'platforms' => $platforms
        ]);
    }

    // Lifecycle Hooks & Helpers
    public function updatedPlatform($value)
    {
        if ($value !== 'custom') {
            $this->rtmp_url = $this->getPlatformUrl($value);
        } else {
            $this->rtmp_url = '';
        }
    }
    
    public function updatedSelectedFile($fileId)
    {
        if ($fileId) {
            $this->selectedFileDetails = UserFile::find($fileId);
            if ($this->selectedFileDetails) {
                $this->stream_name = pathinfo($this->selectedFileDetails->file_name, PATHINFO_FILENAME);
                $this->video_source_id = $this->selectedFileDetails->id;
            }
        } else {
            $this->selectedFileDetails = null;
        }
    }
    
    private function getPlatformUrl($platform)
    {
        return config("services.stream_platforms.{$platform}.rtmp_url", '');
    }

    private function resetInputFields()
    {
        $this->reset([
            'streamId', 'stream_name', 'platform', 'rtmp_url', 'stream_key', 'video_source',
            'video_source_id', 'user_id', 'status', 'description', 'is_loop', 'playlist_order',
            'is_scheduled', 'scheduled_at', 'scheduled_end', 'selectedFile', 'selectedFileDetails'
        ]);
        $this->user_id = Auth::id(); // Default to current user
        $this->platform = 'youtube';
        $this->is_loop = false;
        $this->is_scheduled = false;
    }

    // Modal Controls
    public function create()
    {
        $this->resetInputFields();
        $this->showCreateModal = true;
    }

    public function edit($id)
    {
        $stream = StreamConfiguration::findOrFail($id);
        $this->authorizeAction($stream);

        $this->streamId = $id;
        $this->stream_name = $stream->name;
        $this->platform = $stream->platform;
        $this->rtmp_url = $stream->rtmp_url;
        $this->stream_key = $stream->stream_key;
        $this->video_source_id = $stream->files->first()->id ?? null;
        $this->selectedFile = $this->video_source_id;
        $this->user_id = $stream->user_id;
        $this->status = $stream->status;
        $this->description = $stream->description;
        $this->is_loop = $stream->is_loop;
        $this->playlist_order = $stream->playlist_order;
        $this->is_scheduled = $stream->is_scheduled;
        $this->scheduled_at = $stream->scheduled_at;
        $this->scheduled_end = $stream->scheduled_end;

        $this->updatedSelectedFile($this->selectedFile);
        $this->showCreateModal = true;
    }

    public function store()
    {
        $this->validate();

        if ($this->streamId) {
            $stream = StreamConfiguration::findOrFail($this->streamId);
            $this->authorizeAction($stream);
        } else {
            $stream = new StreamConfiguration();
            $stream->user_id = Auth::user()->isAdmin() ? $this->user_id : Auth::id();
        }

        $stream->name = $this->stream_name;
        $stream->platform = $this->platform;
        $stream->rtmp_url = $this->rtmp_url;
        $stream->stream_key = $this->stream_key;
        $stream->description = $this->description;
        $stream->is_loop = $this->is_loop;
        $stream->playlist_order = $this->playlist_order;
        $stream->is_scheduled = $this->is_scheduled;
        $stream->scheduled_at = $this->is_scheduled ? $this->scheduled_at : null;
        $stream->scheduled_end = $this->is_scheduled ? $this->scheduled_end : null;
        $stream->save();

        $stream->files()->sync([$this->video_source_id]);

        session()->flash('message', $this->streamId ? 'Stream updated successfully.' : 'Stream created successfully.');
        $this->showCreateModal = false;
        $this->resetInputFields();
    }

    public function confirmDelete(StreamConfiguration $stream)
    {
        \Illuminate\Support\Facades\Log::info('StreamManager confirmDelete called', [
            'stream_id' => $stream->id,
            'user_id' => Auth::id(),
            'stream_user_id' => $stream->user_id
        ]);

        $this->authorizeAction($stream);
        $this->streamId = $stream->id;
        $this->showDeleteModal = true;

        \Illuminate\Support\Facades\Log::info('StreamManager delete modal opened', ['stream_id' => $stream->id]);
    }

    public function delete()
    {
        $stream = StreamConfiguration::findOrFail($this->streamId);
        $this->authorizeAction($stream);

        // Stop stream if running
        if (in_array($stream->status, ['STREAMING', 'STARTING'])) {
            $stream->update(['status' => 'STOPPING']);
            StopMultistreamJob::dispatch($stream);
        }

        $stream->delete();
        $this->showDeleteModal = false;
        session()->flash('message', 'Stream deleted successfully.');
    }
    
    // Quick Stream Logic
    public function openQuickStreamModal()
    {
        $this->resetQuickStreamFields();
        $this->showQuickStreamModal = true;
    }

    public function createQuickStream(StreamManagerService $streamManager)
    {
        $this->validate([
            'quickStreamName' => 'required|string|max:255',
            'quickPlatform' => 'required|string|in:youtube,custom',
            'quickStreamKey' => 'required|string',
            'video_source_id' => 'required|exists:user_files,id',
        ]);
        
        $rtmpUrl = $this->quickPlatform === 'custom' ? $this->quickRtmpUrl : $this->getPlatformUrl($this->quickPlatform);
        
        $stream = $streamManager->create([
            'name' => $this->quickStreamName,
            'user_id' => Auth::id(),
            'platform' => $this->quickPlatform,
            'rtmp_url' => $rtmpUrl,
            'stream_key' => $this->quickStreamKey,
            'auto_delete_after_stream_end' => true,
        ], [$this->video_source_id]);

        $streamManager->start($stream->id);

        $this->showQuickStreamModal = false;
        $this->resetQuickStreamFields();
        session()->flash('message', '🚀 Quick Stream đã được tạo và sẽ sớm bắt đầu!');
    }

    private function resetQuickStreamFields()
    {
        $this->reset([
            'quickStreamName', 'quickPlatform', 'quickStreamKey', 'quickVideoSource', 'video_source_id'
        ]);
        $this->quickPlatform = 'youtube';
        $this->quickVideoSource = 'upload';
    }

    // Authorization Helper
    protected function authorizeAction($stream)
    {
        if (!Auth::user()->isAdmin() && $stream->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
