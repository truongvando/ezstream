<?php

namespace App\Livewire;

use App\Models\StreamConfiguration;
use App\Models\UserFile;
use App\Services\PlaylistCommandService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Component để quản lý playlist trong stream đang chạy
 */
class PlaylistManager extends Component
{
    public $stream;
    public $currentFiles = [];
    public $availableFiles = [];
    public $selectedNewFiles = [];
    public $selectedDeleteFiles = [];
    
    public $showAddModal = false;
    public $showDeleteModal = false;
    
    protected $listeners = [
        'refreshPlaylist' => '$refresh',
        'playlistUpdated' => 'handlePlaylistUpdated'
    ];

    public function mount(StreamConfiguration $stream)
    {
        $this->stream = $stream;
        $this->loadCurrentFiles();
        $this->loadAvailableFiles();
    }

    public function loadCurrentFiles()
    {
        if (!$this->stream->video_source_path) {
            $this->currentFiles = [];
            return;
        }

        $fileIds = collect($this->stream->video_source_path)->pluck('file_id')->toArray();
        $this->currentFiles = UserFile::whereIn('id', $fileIds)
            ->orderByRaw("FIELD(id, " . implode(',', $fileIds) . ")")
            ->get()
            ->toArray();
    }

    public function loadAvailableFiles()
    {
        $currentFileIds = collect($this->currentFiles)->pluck('id')->toArray();
        
        $this->availableFiles = UserFile::where('user_id', $this->stream->user_id)
            ->where('status', 'COMPLETED')
            ->whereNotIn('id', $currentFileIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function toggleLoopMode()
    {
        if ($this->stream->status !== 'STREAMING') {
            session()->flash('error', 'Stream không đang chạy');
            return;
        }

        $playlistService = app(PlaylistCommandService::class);
        $result = $playlistService->setLoopMode($this->stream, !$this->stream->loop);

        if ($result['success']) {
            $this->stream->refresh();
            session()->flash('success', 'Đã cập nhật chế độ lặp');
        } else {
            session()->flash('error', 'Lỗi: ' . $result['error']);
        }
    }

    public function setPlaybackOrder($order)
    {
        if ($this->stream->status !== 'STREAMING') {
            session()->flash('error', 'Stream không đang chạy');
            return;
        }

        $playlistService = app(PlaylistCommandService::class);
        $result = $playlistService->setPlaybackOrder($this->stream, $order);

        if ($result['success']) {
            $this->stream->refresh();
            session()->flash('success', 'Đã cập nhật thứ tự phát');
        } else {
            session()->flash('error', 'Lỗi: ' . $result['error']);
        }
    }

    public function openAddModal()
    {
        $this->loadAvailableFiles();
        $this->selectedNewFiles = [];
        $this->showAddModal = true;
    }

    public function closeAddModal()
    {
        $this->showAddModal = false;
        $this->selectedNewFiles = [];
    }

    public function addVideos()
    {
        if (empty($this->selectedNewFiles)) {
            session()->flash('error', 'Vui lòng chọn ít nhất một video');
            return;
        }

        $playlistService = app(PlaylistCommandService::class);
        $result = $playlistService->addVideos($this->stream, $this->selectedNewFiles);

        if ($result['success']) {
            $this->stream->refresh();
            $this->loadCurrentFiles();
            $this->loadAvailableFiles();
            $this->closeAddModal();
            session()->flash('success', 'Đã thêm ' . count($this->selectedNewFiles) . ' video(s) vào playlist');
        } else {
            session()->flash('error', 'Lỗi: ' . $result['error']);
        }
    }

    public function openDeleteModal()
    {
        $this->selectedDeleteFiles = [];
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal()
    {
        $this->showDeleteModal = false;
        $this->selectedDeleteFiles = [];
    }

    public function deleteVideos()
    {
        if (empty($this->selectedDeleteFiles)) {
            session()->flash('error', 'Vui lòng chọn ít nhất một video để xóa');
            return;
        }

        if (count($this->selectedDeleteFiles) >= count($this->currentFiles)) {
            session()->flash('error', 'Không thể xóa tất cả video khỏi playlist');
            return;
        }

        $playlistService = app(PlaylistCommandService::class);
        $result = $playlistService->deleteVideos($this->stream, $this->selectedDeleteFiles);

        if ($result['success']) {
            $this->stream->refresh();
            $this->loadCurrentFiles();
            $this->loadAvailableFiles();
            $this->closeDeleteModal();
            session()->flash('success', 'Đã xóa ' . count($this->selectedDeleteFiles) . ' video(s) khỏi playlist');
        } else {
            session()->flash('error', 'Lỗi: ' . $result['error']);
        }
    }

    public function moveUp($index)
    {
        if ($index <= 0 || $this->stream->status !== 'STREAMING') {
            return;
        }

        $fileIds = collect($this->currentFiles)->pluck('id')->toArray();
        
        // Swap positions
        $temp = $fileIds[$index];
        $fileIds[$index] = $fileIds[$index - 1];
        $fileIds[$index - 1] = $temp;

        $this->updatePlaylistOrder($fileIds);
    }

    public function moveDown($index)
    {
        if ($index >= count($this->currentFiles) - 1 || $this->stream->status !== 'STREAMING') {
            return;
        }

        $fileIds = collect($this->currentFiles)->pluck('id')->toArray();
        
        // Swap positions
        $temp = $fileIds[$index];
        $fileIds[$index] = $fileIds[$index + 1];
        $fileIds[$index + 1] = $temp;

        $this->updatePlaylistOrder($fileIds);
    }

    private function updatePlaylistOrder($fileIds)
    {
        $playlistService = app(PlaylistCommandService::class);
        $result = $playlistService->updatePlaylist($this->stream, $fileIds);

        if ($result['success']) {
            $this->stream->refresh();
            $this->loadCurrentFiles();
            session()->flash('success', 'Đã cập nhật thứ tự playlist');
        } else {
            session()->flash('error', 'Lỗi: ' . $result['error']);
        }
    }

    public function handlePlaylistUpdated($data)
    {
        if (isset($data['stream_id']) && $data['stream_id'] == $this->stream->id) {
            $this->stream->refresh();
            $this->loadCurrentFiles();
            $this->loadAvailableFiles();
        }
    }

    public function getPlaylistStatus()
    {
        $playlistService = app(PlaylistCommandService::class);
        $result = $playlistService->getPlaylistStatus($this->stream);

        if ($result['success']) {
            session()->flash('success', 'Đã gửi yêu cầu kiểm tra trạng thái playlist');
        } else {
            session()->flash('error', 'Lỗi: ' . $result['error']);
        }
    }

    public function render()
    {
        return view('livewire.playlist-manager');
    }
}
