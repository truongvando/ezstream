<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\License;
use App\Models\LicenseActivation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.sidebar')]
class LicenseManager extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all';
    public $showDeactivateModal = false;
    public $selectedLicense = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all']
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function showDeactivateModal($licenseId)
    {
        $this->selectedLicense = License::where('id', $licenseId)
                                       ->where('user_id', Auth::id())
                                       ->first();
        
        if ($this->selectedLicense) {
            $this->showDeactivateModal = true;
        }
    }

    public function closeDeactivateModal()
    {
        $this->showDeactivateModal = false;
        $this->selectedLicense = null;
    }

    public function deactivateLicense()
    {
        if (!$this->selectedLicense) {
            session()->flash('error', 'License không tồn tại');
            return;
        }

        try {
            // Delete all activations
            $this->selectedLicense->activations()->delete();
            
            // Reset device info
            $this->selectedLicense->update([
                'device_id' => null,
                'device_name' => null,
                'device_info' => null,
                'activated_at' => null
            ]);

            Log::info('License deactivated', [
                'license_id' => $this->selectedLicense->id,
                'user_id' => Auth::id(),
                'tool' => $this->selectedLicense->tool->name
            ]);

            session()->flash('success', 'Đã hủy kích hoạt license thành công. Bạn có thể kích hoạt lại trên thiết bị khác.');
            
            $this->closeDeactivateModal();

        } catch (\Exception $e) {
            Log::error('Error deactivating license', [
                'license_id' => $this->selectedLicense->id,
                'error' => $e->getMessage()
            ]);

            session()->flash('error', 'Có lỗi xảy ra khi hủy kích hoạt license.');
        }
    }

    public function copyLicenseKey($licenseKey)
    {
        // This will be handled by JavaScript
        $this->dispatch('copy-to-clipboard', $licenseKey);
        session()->flash('success', 'Đã copy license key vào clipboard!');
    }

    public function render()
    {
        $query = License::where('user_id', Auth::id())
                       ->with(['tool', 'activations']);

        // Search filter
        if ($this->search) {
            $query->whereHas('tool', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            });
        }

        // Status filter
        switch ($this->statusFilter) {
            case 'active':
                $query->where('is_active', true);
                break;
            case 'expired':
                $query->where('expires_at', '<', now());
                break;
            case 'activated':
                $query->whereNotNull('activated_at');
                break;
            case 'not_activated':
                $query->whereNull('activated_at');
                break;
        }

        $licenses = $query->orderBy('created_at', 'desc')->paginate(10);

        // Get statistics
        $stats = [
            'total_licenses' => License::where('user_id', Auth::id())->count(),
            'active_licenses' => License::where('user_id', Auth::id())->where('is_active', true)->count(),
            'activated_licenses' => License::where('user_id', Auth::id())->whereNotNull('activated_at')->count(),
            'expired_licenses' => License::where('user_id', Auth::id())->where('expires_at', '<', now())->count(),
        ];

        return view('livewire.license-manager', [
            'licenses' => $licenses,
            'stats' => $stats
        ]);
    }
}
