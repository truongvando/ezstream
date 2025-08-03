<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\MmoService;
use App\Models\MmoOrder;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.sidebar')]
class MmoServiceManager extends Component
{
    use WithPagination;

    public $search = '';
    public $categoryFilter = '';
    public $statusFilter = '';

    // Service form
    public $showServiceModal = false;
    public $editingServiceId = null;
    public $serviceName = '';
    public $serviceDescription = '';
    public $serviceDetailedDescription = '';
    public $servicePrice = '';
    public $serviceCategory = 'MMO';
    public $serviceFeatures = '';
    public $serviceDeliveryTime = '1-24 hours';
    public $serviceImageUrl = '';
    public $serviceIsActive = true;
    public $serviceIsFeatured = false;
    public $serviceSortOrder = 0;
    public $serviceRequirements = '';
    public $serviceNotes = '';

    protected $rules = [
        'serviceName' => 'required|string|max:255',
        'serviceDescription' => 'required|string|max:1000',
        'serviceDetailedDescription' => 'nullable|string',
        'servicePrice' => 'required|numeric|min:0.01|max:10000',
        'serviceCategory' => 'required|string|max:100',
        'serviceFeatures' => 'nullable|string',
        'serviceDeliveryTime' => 'required|string|max:100',
        'serviceImageUrl' => 'nullable|url|max:500',
        'serviceSortOrder' => 'nullable|integer|min:0|max:999',
        'serviceRequirements' => 'nullable|string',
        'serviceNotes' => 'nullable|string'
    ];

    public function openServiceModal($serviceId = null)
    {
        if ($serviceId) {
            $service = MmoService::findOrFail($serviceId);
            $this->editingServiceId = $serviceId;
            $this->serviceName = $service->name;
            $this->serviceDescription = $service->description;
            $this->serviceDetailedDescription = $service->detailed_description;
            $this->servicePrice = $service->price;
            $this->serviceCategory = $service->category;
            $this->serviceFeatures = $service->features ? implode(', ', $service->features) : '';
            $this->serviceDeliveryTime = $service->delivery_time;
            $this->serviceImageUrl = $service->image_url;
            $this->serviceIsActive = $service->is_active;
            $this->serviceIsFeatured = $service->is_featured;
            $this->serviceSortOrder = $service->sort_order;
            $this->serviceRequirements = $service->requirements ? implode(', ', $service->requirements) : '';
            $this->serviceNotes = $service->notes;
        } else {
            $this->reset([
                'editingServiceId', 'serviceName', 'serviceDescription', 'serviceDetailedDescription',
                'servicePrice', 'serviceCategory', 'serviceFeatures', 'serviceDeliveryTime',
                'serviceImageUrl', 'serviceIsActive', 'serviceIsFeatured', 'serviceSortOrder',
                'serviceRequirements', 'serviceNotes'
            ]);
            $this->serviceCategory = 'MMO';
            $this->serviceDeliveryTime = '1-24 hours';
            $this->serviceIsActive = true;
            $this->serviceIsFeatured = false;
            $this->serviceSortOrder = 0;
        }
        $this->showServiceModal = true;
    }

    public function closeServiceModal()
    {
        $this->showServiceModal = false;
        $this->reset([
            'editingServiceId', 'serviceName', 'serviceDescription', 'serviceDetailedDescription',
            'servicePrice', 'serviceCategory', 'serviceFeatures', 'serviceDeliveryTime',
            'serviceImageUrl', 'serviceIsActive', 'serviceIsFeatured', 'serviceSortOrder',
            'serviceRequirements', 'serviceNotes'
        ]);
    }

    public function saveService()
    {
        $this->validate();

        try {
            $data = [
                'name' => $this->serviceName,
                'description' => $this->serviceDescription,
                'detailed_description' => $this->serviceDetailedDescription,
                'price' => $this->servicePrice,
                'category' => $this->serviceCategory,
                'features' => $this->serviceFeatures ? array_map('trim', explode(',', $this->serviceFeatures)) : null,
                'delivery_time' => $this->serviceDeliveryTime,
                'image_url' => $this->serviceImageUrl ?: null,
                'is_active' => $this->serviceIsActive,
                'is_featured' => $this->serviceIsFeatured,
                'sort_order' => $this->serviceSortOrder,
                'requirements' => $this->serviceRequirements ? array_map('trim', explode(',', $this->serviceRequirements)) : null,
                'notes' => $this->serviceNotes
            ];

            if ($this->editingServiceId) {
                MmoService::findOrFail($this->editingServiceId)->update($data);
                session()->flash('success', '✅ Đã cập nhật dịch vụ MMO');
            } else {
                MmoService::create($data);
                session()->flash('success', '✅ Đã tạo dịch vụ MMO mới');
            }

            $this->closeServiceModal();

        } catch (\Exception $e) {
            Log::error('MMO Service save failed', [
                'error' => $e->getMessage(),
                'admin_id' => auth()->id()
            ]);
            session()->flash('error', 'Có lỗi xảy ra khi lưu dịch vụ');
        }
    }

    public function toggleActive($serviceId)
    {
        try {
            $service = MmoService::findOrFail($serviceId);
            $service->update(['is_active' => !$service->is_active]);

            $status = $service->is_active ? 'kích hoạt' : 'tắt';
            session()->flash('success', "✅ Đã {$status} dịch vụ {$service->name}");

        } catch (\Exception $e) {
            session()->flash('error', 'Có lỗi xảy ra');
        }
    }

    public function toggleFeatured($serviceId)
    {
        try {
            $service = MmoService::findOrFail($serviceId);
            $service->update(['is_featured' => !$service->is_featured]);

            $status = $service->is_featured ? 'nổi bật' : 'bình thường';
            session()->flash('success', "✅ Đã đặt dịch vụ {$service->name} là {$status}");

        } catch (\Exception $e) {
            session()->flash('error', 'Có lỗi xảy ra');
        }
    }

    public function deleteService($serviceId)
    {
        try {
            $service = MmoService::findOrFail($serviceId);

            // Check if has orders
            if ($service->orders()->count() > 0) {
                session()->flash('error', 'Không thể xóa dịch vụ đã có đơn hàng');
                return;
            }

            $service->delete();
            session()->flash('success', "✅ Đã xóa dịch vụ {$service->name}");

        } catch (\Exception $e) {
            session()->flash('error', 'Có lỗi xảy ra khi xóa dịch vụ');
        }
    }

    public function render()
    {
        $query = MmoService::query()
                          ->when($this->search, function($q) {
                              $q->where('name', 'like', '%' . $this->search . '%')
                                ->orWhere('description', 'like', '%' . $this->search . '%')
                                ->orWhere('category', 'like', '%' . $this->search . '%');
                          })
                          ->when($this->categoryFilter, function($q) {
                              $q->where('category', $this->categoryFilter);
                          })
                          ->when($this->statusFilter !== '', function($q) {
                              if ($this->statusFilter === 'active') {
                                  $q->where('is_active', true);
                              } elseif ($this->statusFilter === 'inactive') {
                                  $q->where('is_active', false);
                              } elseif ($this->statusFilter === 'featured') {
                                  $q->where('is_featured', true);
                              }
                          })
                          ->orderBy('sort_order')
                          ->orderBy('created_at', 'desc');

        $services = $query->paginate(15);

        // Statistics
        $stats = [
            'total_services' => MmoService::count(),
            'active_services' => MmoService::where('is_active', true)->count(),
            'featured_services' => MmoService::where('is_featured', true)->count(),
            'pending_orders' => MmoOrder::where('status', 'PENDING')->count(),
            'processing_orders' => MmoOrder::where('status', 'PROCESSING')->count(),
            'total_revenue' => MmoOrder::where('status', 'COMPLETED')->sum('amount')
        ];

        // Categories
        $categories = MmoService::select('category')
                                ->distinct()
                                ->pluck('category')
                                ->filter()
                                ->sort()
                                ->values();

        return view('livewire.admin.mmo-service-manager', [
            'services' => $services,
            'stats' => $stats,
            'categories' => $categories
        ]);
    }
}
