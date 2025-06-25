<?php

namespace App\Livewire;

use App\Models\StreamConfiguration;
use App\Services\SshService;
use Livewire\Component;

class LogViewerModal extends Component
{
    public ?StreamConfiguration $stream = null;
    public $logContent = '';
    public $showModal = false;

    protected $listeners = ['showLogModal'];

    public function showLogModal($streamId)
    {
        $this->stream = StreamConfiguration::with('vpsServer')->find($streamId);
        $this->loadLogContent();
        $this->showModal = true;
    }

    public function loadLogContent()
    {
        if (!$this->stream || !$this->stream->output_log_path) {
            $this->logContent = 'Log file not available.';
            return;
        }

        $ssh = new SshService();
        if ($ssh->connect($this->stream->vpsServer)) {
            $this->logContent = $ssh->readFile($this->stream->output_log_path) ?? 'Could not read log file or file is empty.';
            $ssh->disconnect();
        } else {
            $this->logContent = 'Could not connect to VPS to retrieve log.';
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->logContent = '';
        $this->stream = null;
    }

    public function render()
    {
        return view('livewire.log-viewer-modal');
    }
}
