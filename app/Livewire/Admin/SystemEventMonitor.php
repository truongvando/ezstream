<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\SystemEvent;

class SystemEventMonitor extends Component
{
    public function render()
    {
        $events = SystemEvent::latest()->take(30)->get();

        return view('livewire.admin.system-event-monitor', [
            'events' => $events,
        ]);
    }
}
