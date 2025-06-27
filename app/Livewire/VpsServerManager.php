<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\VpsServer;
use App\Jobs\ProvisionVpsJob;

class VpsServerManager extends Component
{
    use WithPagination;

    public $showModal = false;
    public $editingServer = null;
    
    // Form fields
    public $name = '';
    public $ip_address = '';
    public $ssh_user = 'root';
    public $ssh_password = '';
    public $ssh_port = 22;
    public $is_active = true;
    public $description = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'ip_address' => 'required|ip|unique:vps_servers,ip_address',
        'ssh_user' => 'required|string|max:255',
        'ssh_password' => 'required|string|min:6',
        'ssh_port' => 'required|integer|min:1|max:65535',
        'is_active' => 'boolean',
        'description' => 'nullable|string',
    ];

    public function render()
    {
        return view('livewire.vps-server-manager', [
            'servers' => VpsServer::with('latestStat')->paginate(10)
        ])->layout('layouts.sidebar')
          ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">VPS Servers</h1>');
    }

    public function openModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editingServer = null;
        $this->name = '';
        $this->ip_address = '';
        $this->ssh_user = 'root';
        $this->ssh_password = '';
        $this->ssh_port = 22;
        $this->is_active = true;
        $this->description = '';
        $this->resetErrorBag();
    }

    public function save()
    {
        $rules = $this->rules;
        
        // If editing, ignore unique validation for current record
        if ($this->editingServer) {
            $rules['ip_address'] = 'required|ip|unique:vps_servers,ip_address,' . $this->editingServer->id;
        }

        $validatedData = $this->validate($rules);

        if ($this->editingServer) {
            $this->editingServer->update($validatedData);
            session()->flash('message', 'VPS Server đã được cập nhật thành công!');
        } else {
            $server = VpsServer::create($validatedData);
            ProvisionVpsJob::dispatch($server);
            session()->flash('message', 'VPS Server đã được thêm và đang được cài đặt tự động!');
        }

        $this->closeModal();
    }

    public function edit(VpsServer $server)
    {
        $this->editingServer = $server;
        $this->name = $server->name;
        $this->ip_address = $server->ip_address;
        $this->ssh_user = $server->ssh_user;
        $this->ssh_password = ''; // Don't show encrypted password
        $this->ssh_port = $server->ssh_port;
        $this->is_active = $server->is_active;
        $this->description = $server->description ?? '';
        $this->showModal = true;
    }

    public function delete(VpsServer $server)
    {
        $server->delete();
        session()->flash('message', 'VPS Server đã được xóa thành công!');
    }

    public function toggleStatus(VpsServer $server)
    {
        $server->update(['is_active' => !$server->is_active]);
        session()->flash('message', 'Trạng thái VPS Server đã được cập nhật!');
    }
}
