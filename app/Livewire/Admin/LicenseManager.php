<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\User;
use App\Models\Tool;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.sidebar')]
class LicenseManager extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all';
    public $toolFilter = 'all';
    public $showCreateModal = false;
    public $showEditModal = false;
    public $selectedLicense = null;

    // Form fields
    public $user_id = '';
    public $tool_id = '';
    public $license_key = '';
    public $device_id = '';
    public $device_name = '';
    public $device_info = '';
    public $is_active = true;
    public $expires_at = '';

    protected $rules = [
        'user_id' => 'required|exists:users,id',
        'tool_id' => 'required|exists:tools,id',
        'license_key' => 'required|string|unique:licenses,license_key',
        'device_id' => 'nullable|string',
        'device_name' => 'nullable|string',
        'is_active' => 'boolean',
        'expires_at' => 'nullable|date'
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingToolFilter()
    {
        $this->resetPage();
    }

    public function showCreateModal()
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function showEditModal($licenseId)
    {
        $license = License::findOrFail($licenseId);
        $this->selectedLicense = $license;
        
        $this->user_id = $license->user_id;
        $this->tool_id = $license->tool_id;
        $this->license_key = $license->license_key;
        $this->device_id = $license->device_id;
        $this->device_name = $license->device_name;
        $this->device_info = is_array($license->device_info) ? json_encode($license->device_info) : $license->device_info;
        $this->is_active = $license->is_active;
        $this->expires_at = $license->expires_at ? $license->expires_at->format('Y-m-d') : '';
        
        $this->showEditModal = true;
    }

    public function closeModals()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->selectedLicense = null;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->user_id = '';
        $this->tool_id = '';
        $this->license_key = '';
        $this->device_id = '';
        $this->device_name = '';
        $this->device_info = '';
        $this->is_active = true;
        $this->expires_at = '';
    }

    public function generateLicenseKey()
    {
        $this->license_key = strtoupper(
            \Illuminate\Support\Str::random(4) . '-' . 
            \Illuminate\Support\Str::random(4) . '-' . 
            \Illuminate\Support\Str::random(4) . '-' . 
            \Illuminate\Support\Str::random(4)
        );
    }

    public function createLicense()
    {
        $this->validate();

        try {
            License::create([
                'user_id' => $this->user_id,
                'tool_id' => $this->tool_id,
                'license_key' => $this->license_key,
                'device_id' => $this->device_id ?: null,
                'device_name' => $this->device_name ?: null,
                'device_info' => $this->device_info ? json_decode($this->device_info, true) : null,
                'is_active' => $this->is_active,
                'expires_at' => $this->expires_at ? \Carbon\Carbon::parse($this->expires_at) : null,
            ]);

            session()->flash('success', 'License created successfully!');
            $this->closeModals();

        } catch (\Exception $e) {
            Log::error('Error creating license: ' . $e->getMessage());
            session()->flash('error', 'Error creating license.');
        }
    }

    public function updateLicense()
    {
        $this->validate([
            'license_key' => 'required|string|unique:licenses,license_key,' . $this->selectedLicense->id,
        ] + array_slice($this->rules, 1));

        try {
            $this->selectedLicense->update([
                'user_id' => $this->user_id,
                'tool_id' => $this->tool_id,
                'license_key' => $this->license_key,
                'device_id' => $this->device_id ?: null,
                'device_name' => $this->device_name ?: null,
                'device_info' => $this->device_info ? json_decode($this->device_info, true) : null,
                'is_active' => $this->is_active,
                'expires_at' => $this->expires_at ? \Carbon\Carbon::parse($this->expires_at) : null,
            ]);

            session()->flash('success', 'License updated successfully!');
            $this->closeModals();

        } catch (\Exception $e) {
            Log::error('Error updating license: ' . $e->getMessage());
            session()->flash('error', 'Error updating license.');
        }
    }

    public function deleteLicense($licenseId)
    {
        try {
            $license = License::findOrFail($licenseId);
            $license->delete();
            
            session()->flash('success', 'License deleted successfully!');

        } catch (\Exception $e) {
            Log::error('Error deleting license: ' . $e->getMessage());
            session()->flash('error', 'Error deleting license.');
        }
    }

    public function toggleStatus($licenseId)
    {
        try {
            $license = License::findOrFail($licenseId);
            $license->update(['is_active' => !$license->is_active]);
            
            $status = $license->is_active ? 'activated' : 'deactivated';
            session()->flash('success', "License {$status} successfully!");

        } catch (\Exception $e) {
            Log::error('Error toggling license status: ' . $e->getMessage());
            session()->flash('error', 'Error updating license status.');
        }
    }

    public function revokeLicense($licenseId)
    {
        try {
            $license = License::findOrFail($licenseId);
            $license->update([
                'is_active' => false,
                'device_id' => null,
                'device_name' => null,
                'device_info' => null,
                'activated_at' => null
            ]);
            
            session()->flash('success', 'License revoked successfully!');

        } catch (\Exception $e) {
            Log::error('Error revoking license: ' . $e->getMessage());
            session()->flash('error', 'Error revoking license.');
        }
    }

    public function render()
    {
        $query = License::with(['user', 'tool']);

        // Search filter
        if ($this->search) {
            $query->where(function($q) {
                $q->where('license_key', 'like', '%' . $this->search . '%')
                  ->orWhere('device_name', 'like', '%' . $this->search . '%')
                  ->orWhereHas('user', function($userQuery) {
                      $userQuery->where('name', 'like', '%' . $this->search . '%')
                               ->orWhere('email', 'like', '%' . $this->search . '%');
                  })
                  ->orWhereHas('tool', function($toolQuery) {
                      $toolQuery->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        // Status filter
        switch ($this->statusFilter) {
            case 'active':
                $query->where('is_active', true);
                break;
            case 'inactive':
                $query->where('is_active', false);
                break;
            case 'activated':
                $query->whereNotNull('activated_at');
                break;
            case 'not_activated':
                $query->whereNull('activated_at');
                break;
            case 'expired':
                $query->where('expires_at', '<', now());
                break;
        }

        // Tool filter
        if ($this->toolFilter !== 'all') {
            $query->where('tool_id', $this->toolFilter);
        }

        $licenses = $query->orderBy('created_at', 'desc')->paginate(15);

        // Get statistics
        $stats = [
            'total_licenses' => License::count(),
            'active_licenses' => License::where('is_active', true)->count(),
            'activated_licenses' => License::whereNotNull('activated_at')->count(),
            'expired_licenses' => License::where('expires_at', '<', now())->count(),
        ];

        // Get data for dropdowns
        $users = User::orderBy('name')->get();
        $tools = Tool::where('is_active', true)->orderBy('name')->get();

        return view('livewire.admin.license-manager', [
            'licenses' => $licenses,
            'stats' => $stats,
            'users' => $users,
            'tools' => $tools
        ]);
    }
}
