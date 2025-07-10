<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\VpsServer;
use App\Jobs\ProvisionVpsJob;
use App\Services\SshService;
use Illuminate\Support\Facades\Log;

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
    
    // Logs modal
    public $showLogsModal = false;
    public $selectedServer = null;
    public $selectedServerName = '';
    public $selectedLogType = 'provision';
    public $logsContent = '';
    public $logsError = '';

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
        // Fixed ambiguous column query
        $servers = VpsServer::select([
                'vps_servers.id', 
                'vps_servers.name', 
                'vps_servers.ip_address', 
                'vps_servers.ssh_user', 
                'vps_servers.ssh_port', 
                'vps_servers.is_active', 
                'vps_servers.status', 
                'vps_servers.description', 
                'vps_servers.created_at'
            ])
            ->with(['latestStat' => function($query) {
                $query->select([
                    'vps_stats.vps_server_id', 
                    'vps_stats.cpu_usage_percent', 
                    'vps_stats.ram_usage_percent', 
                    'vps_stats.disk_usage_percent', 
                    'vps_stats.created_at'
                ]);
            }])
            ->orderBy('vps_servers.created_at', 'desc')
            ->paginate(10);

        return view('livewire.vps-server-manager', [
            'servers' => $servers
        ])
            ->layout('layouts.sidebar')
            ->slot('header', '<h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Quản lý VPS Servers</h1>');
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

        try {
            if ($this->editingServer) {
                $this->editingServer->update($validatedData);
                session()->flash('message', 'VPS Server đã được cập nhật thành công!');
            } else {
                $server = VpsServer::create($validatedData);
                ProvisionVpsJob::dispatch($server);
                session()->flash('message', 'VPS Server đã được thêm và đang được cài đặt tự động!');
            }

            $this->closeModal();

            // Force refresh the component
            $this->dispatch('vps-updated');

        } catch (\Exception $e) {
            Log::error('VPS save error: ' . $e->getMessage());
            session()->flash('error', 'Có lỗi xảy ra: ' . $e->getMessage());
        }
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

    public function viewLogs(VpsServer $server)
    {
        $this->selectedServer = $server;
        $this->selectedServerName = $server->name;
        $this->logsError = '';
        $this->logsContent = 'Đang tải logs...';
        $this->showLogsModal = true;
    }

    public function closeLogsModal()
    {
        $this->showLogsModal = false;
        $this->selectedServer = null;
        $this->selectedServerName = '';
        $this->logsContent = '';
        $this->logsError = '';
    }

    public function refreshLogs()
    {
        if (!$this->selectedServer) {
            return;
        }

        $this->logsContent = '';
        $this->logsError = '';

        try {
            $sshService = new SshService();
            
            if (!$sshService->connect($this->selectedServer)) {
                $this->logsError = 'Không thể kết nối SSH tới server này.';
                return;
            }

            $logPath = match($this->selectedLogType) {
                'provision' => '/opt/streaming_agent/provision.log',
                'system' => '/var/log/syslog',
                'streaming' => '/opt/streaming_agent/streaming.log',
                default => '/opt/streaming_agent/provision.log'
            };

            // Read last 100 lines of the log file
            $this->logsContent = $sshService->execute("tail -n 100 " . escapeshellarg($logPath) . " 2>/dev/null || echo 'Log file not found or empty'");
            
            if (empty(trim($this->logsContent))) {
                $this->logsContent = "Log file trống hoặc không tồn tại.\n\nFile path: " . $logPath;
            }

            $sshService->disconnect();

        } catch (\Exception $e) {
            Log::error('Error reading VPS logs', [
                'server_id' => $this->selectedServer->id,
                'log_type' => $this->selectedLogType,
                'error' => $e->getMessage()
            ]);
            
            $this->logsError = 'Lỗi khi đọc logs: ' . $e->getMessage();
        }
    }

    public function updatedSelectedLogType()
    {
        $this->refreshLogs();
    }
}
