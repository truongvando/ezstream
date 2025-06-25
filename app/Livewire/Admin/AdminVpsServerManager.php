<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\VpsServer;

class AdminVpsServerManager extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.admin.vps-server-manager', [
            'servers' => VpsServer::with('latestStat')->paginate(10)
        ])->layout('layouts.admin');
    }

    public function delete(VpsServer $server)
    {
        if ($server->streamConfigurations()->count() > 0) {
            session()->flash('error', 'Không thể xóa VPS đang có luồng stream hoạt động.');
            return;
        }
        $server->delete();
        session()->flash('message', 'VPS Server đã được xóa thành công!');
    }
    
    public function toggleStatus(VpsServer $server)
    {
        $server->update(['is_active' => !$server->is_active]);
        session()->flash('message', 'Trạng thái VPS Server đã được cập nhật!');
    }
}
