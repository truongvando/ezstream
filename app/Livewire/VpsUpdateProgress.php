<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Redis;
use Livewire\Component;

class VpsUpdateProgress extends Component
{
    public $vpsId;
    public $progress = null;
    public $isVisible = false;

    protected $listeners = ['showUpdateProgress', 'hideUpdateProgress'];

    public function mount($vpsId = null)
    {
        $this->vpsId = $vpsId;
    }

    public function showUpdateProgress($vpsId)
    {
        $this->vpsId = $vpsId;
        $this->isVisible = true;
        $this->loadProgress();
    }

    public function hideUpdateProgress()
    {
        $this->isVisible = false;
        $this->progress = null;
    }

    public function loadProgress()
    {
        if (!$this->vpsId) {
            return;
        }

        try {
            $key = "vps_update_progress:{$this->vpsId}";
            $progressJson = Redis::get($key);
            
            if ($progressJson) {
                $this->progress = json_decode($progressJson, true);
                
                // Auto-hide after completion
                if ($this->progress['progress_percentage'] >= 100) {
                    $this->dispatch('update-completed');
                }
            } else {
                $this->progress = null;
            }
            
        } catch (\Exception $e) {
            \Log::error("Failed to load VPS update progress: {$e->getMessage()}");
            $this->progress = null;
        }
    }

    public function render()
    {
        // Auto-refresh progress every 2 seconds when visible
        if ($this->isVisible && $this->vpsId) {
            $this->loadProgress();
        }

        return view('livewire.vps-update-progress');
    }
}
