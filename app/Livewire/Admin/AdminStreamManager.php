<?php

namespace App\Livewire\Admin;

use App\Models\StreamConfiguration;
use App\Models\User;
use App\Models\VpsServer;
use App\Jobs\StopMultistreamJob;
use App\Jobs\CleanupStreamFilesJob;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;

class AdminStreamManager extends Component
{
    use WithPagination;

    public $filterUserId = '';
    public $filterStatus = '';
    public $search = '';
    public $showDeleteModal = false;
    public ?StreamConfiguration $deletingStream = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterUserId' => ['except' => ''],
        'filterStatus' => ['except' => ''],
    ];

    public function updating()
    {
        $this->resetPage();
    }

    public function refreshStreams()
    {
        // This method is called by wire:poll to trigger a re-render.
        // It doesn't need to do anything else.
    }

    public function confirmDelete(StreamConfiguration $stream)
    {
        $this->deletingStream = $stream;
        $this->showDeleteModal = true;
    }

    public function delete()
    {
        if (!$this->deletingStream) {
            return;
        }

        if (in_array($this->deletingStream->status, ['STREAMING', 'STARTING'])) {
            StopMultistreamJob::dispatch($this->deletingStream);
        }

        if ($this->deletingStream->vps_server_id) {
            CleanupStreamFilesJob::dispatch($this->deletingStream);
        }

        $this->deletingStream->delete();
        $this->showDeleteModal = false;
        $this->deletingStream = null;
        session()->flash('success', 'Stream đã được xóa thành công.');
    }

    public function forceStopStream(StreamConfiguration $stream)
    {
        $stream->update([
            'status' => 'STOPPED',
            'output_log' => ($stream->output_log ?? '') . "\nForce stopped by admin.",
            'last_stopped_at' => now(),
            'ffmpeg_pid' => null,
        ]);

        if ($stream->vps_server_id) {
            $vps = $stream->vpsServer;
            if ($vps && $vps->current_streams > 0) {
                $vps->decrement('current_streams');
            }
        }

        session()->flash('success', "Stream '{$stream->title}' đã được buộc dừng.");
    }

    public function render()
    {
        $query = StreamConfiguration::with(['user', 'vpsServer'])
            ->latest('updated_at');

        if ($this->filterUserId) {
            $query->where('user_id', $this->filterUserId);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', '%' . $this->search . '%')
                  ->orWhereHas('user', function ($userQuery) {
                      $userQuery->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        $streams = $query->paginate(15);
        $users = User::orderBy('name')->get();
        $statuses = ['INACTIVE', 'STARTING', 'STREAMING', 'STOPPING', 'STOPPED', 'ERROR'];

        return view('livewire.admin.admin-stream-manager', [
            'streams' => $streams,
            'users' => $users,
            'statuses' => $statuses,
        ])->layout('layouts.sidebar');
    }
}
