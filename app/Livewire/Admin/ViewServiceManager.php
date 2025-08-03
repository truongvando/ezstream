<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Models\ApiService;
use App\Models\ViewOrder;
use App\Services\JustAnotherPanelService;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.sidebar')]
class ViewServiceManager extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all';
    public $categoryFilter = 'all';
    public $showCreateModal = false;
    public $showEditModal = false;
    public $selectedService = null;

    // Form fields
    public $service_id = '';
    public $name = '';
    public $type = '';
    public $category = '';
    public $rate = '';
    public $min_quantity = '';
    public $max_quantity = '';
    public $refill = false;
    public $cancel = false;
    public $markup_percentage = 20;
    public $is_active = true;

    protected $rules = [
        'service_id' => 'required|integer|unique:api_services,service_id',
        'name' => 'required|string|max:255',
        'type' => 'required|string|max:100',
        'category' => 'required|string|max:100',
        'rate' => 'required|numeric|min:0',
        'min_quantity' => 'required|integer|min:1',
        'max_quantity' => 'required|integer|min:1',
        'refill' => 'boolean',
        'cancel' => 'boolean',
        'markup_percentage' => 'required|integer|min:0|max:100',
        'is_active' => 'boolean'
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter()
    {
        $this->resetPage();
    }

    public function syncFromAPI()
    {
        try {
            $japService = new JustAnotherPanelService();
            $result = $japService->syncServices();

            if ($result['success']) {
                session()->flash('success', "Synced {$result['synced']} services from API. {$result['updated']} updated, {$result['created']} created.");
            } else {
                session()->flash('error', 'Error syncing services: ' . $result['message']);
            }

        } catch (\Exception $e) {
            Log::error('Error syncing services from API: ' . $e->getMessage());
            session()->flash('error', 'Error syncing services from API.');
        }
    }

    public function showCreateModal()
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function showEditModal($serviceId)
    {
        $service = ApiService::findOrFail($serviceId);
        $this->selectedService = $service;
        
        $this->service_id = $service->service_id;
        $this->name = $service->name;
        $this->type = $service->type;
        $this->category = $service->category;
        $this->rate = $service->rate;
        $this->min_quantity = $service->min_quantity;
        $this->max_quantity = $service->max_quantity;
        $this->refill = $service->refill;
        $this->cancel = $service->cancel;
        $this->markup_percentage = $service->markup_percentage;
        $this->is_active = $service->is_active;
        
        $this->showEditModal = true;
    }

    public function closeModals()
    {
        $this->showCreateModal = false;
        $this->showEditModal = false;
        $this->selectedService = null;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->service_id = '';
        $this->name = '';
        $this->type = '';
        $this->category = '';
        $this->rate = '';
        $this->min_quantity = '';
        $this->max_quantity = '';
        $this->refill = false;
        $this->cancel = false;
        $this->markup_percentage = 20;
        $this->is_active = true;
    }

    public function createService()
    {
        $this->validate();

        try {
            ApiService::create([
                'service_id' => $this->service_id,
                'name' => $this->name,
                'type' => $this->type,
                'category' => $this->category,
                'rate' => $this->rate,
                'min_quantity' => $this->min_quantity,
                'max_quantity' => $this->max_quantity,
                'refill' => $this->refill,
                'cancel' => $this->cancel,
                'markup_percentage' => $this->markup_percentage,
                'is_active' => $this->is_active,
            ]);

            session()->flash('success', 'Service created successfully!');
            $this->closeModals();

        } catch (\Exception $e) {
            Log::error('Error creating service: ' . $e->getMessage());
            session()->flash('error', 'Error creating service.');
        }
    }

    public function updateService()
    {
        $this->validate([
            'service_id' => 'required|integer|unique:api_services,service_id,' . $this->selectedService->id,
        ] + array_slice($this->rules, 1));

        try {
            $this->selectedService->update([
                'service_id' => $this->service_id,
                'name' => $this->name,
                'type' => $this->type,
                'category' => $this->category,
                'rate' => $this->rate,
                'min_quantity' => $this->min_quantity,
                'max_quantity' => $this->max_quantity,
                'refill' => $this->refill,
                'cancel' => $this->cancel,
                'markup_percentage' => $this->markup_percentage,
                'is_active' => $this->is_active,
            ]);

            session()->flash('success', 'Service updated successfully!');
            $this->closeModals();

        } catch (\Exception $e) {
            Log::error('Error updating service: ' . $e->getMessage());
            session()->flash('error', 'Error updating service.');
        }
    }

    public function deleteService($serviceId)
    {
        try {
            $service = ApiService::findOrFail($serviceId);
            
            // Check if service has orders
            $orderCount = ViewOrder::where('service_id', $serviceId)->count();
            if ($orderCount > 0) {
                session()->flash('error', "Cannot delete service. It has {$orderCount} orders.");
                return;
            }

            $service->delete();
            session()->flash('success', 'Service deleted successfully!');

        } catch (\Exception $e) {
            Log::error('Error deleting service: ' . $e->getMessage());
            session()->flash('error', 'Error deleting service.');
        }
    }

    public function toggleStatus($serviceId)
    {
        try {
            $service = ApiService::findOrFail($serviceId);
            $service->update(['is_active' => !$service->is_active]);
            
            $status = $service->is_active ? 'activated' : 'deactivated';
            session()->flash('success', "Service {$status} successfully!");

        } catch (\Exception $e) {
            Log::error('Error toggling service status: ' . $e->getMessage());
            session()->flash('error', 'Error updating service status.');
        }
    }

    public function render()
    {
        $query = ApiService::query();

        // Search filter
        if ($this->search) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('category', 'like', '%' . $this->search . '%')
                  ->orWhere('type', 'like', '%' . $this->search . '%');
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
        }

        // Category filter
        if ($this->categoryFilter !== 'all') {
            $query->where('category', $this->categoryFilter);
        }

        $paginatedServices = $query->withCount('viewOrders')
                                   ->orderBy('category')
                                   ->orderBy('name')
                                   ->paginate(15);

        // Group services by category for dropdown
        $allServices = ApiService::where('is_active', true)
                                ->orderBy('category')
                                ->orderBy('name')
                                ->get()
                                ->groupBy('category');

        // Get statistics
        $stats = [
            'total_services' => ApiService::count(),
            'active_services' => ApiService::where('is_active', true)->count(),
            'total_orders' => ViewOrder::count(),
            'completed_orders' => ViewOrder::where('status', 'COMPLETED')->count(),
            'categories' => ApiService::distinct('category')->pluck('category'),
        ];

        return view('livewire.admin.view-service-manager', [
            'paginatedServices' => $paginatedServices,
            'services' => $allServices, // For dropdown
            'stats' => $stats
        ]);
    }
}
