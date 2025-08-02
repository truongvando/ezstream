<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\ApiService;
use App\Models\ViewOrder;
use App\Models\Transaction;
use App\Jobs\ProcessViewOrderJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.sidebar')]
class ViewServiceManager extends Component
{
    public $selectedService = null;
    public $link = '';
    public $quantity = 100;
    public $services = [];
    public $categories = [];
    public $selectedCategory = '';
    public $searchTerm = '';

    protected $rules = [
        'selectedService' => 'required|exists:api_services,id',
        'link' => 'required|url|max:500',
        'quantity' => 'required|integer|min:1'
    ];

    protected $messages = [
        'selectedService.required' => 'Vui lòng chọn dịch vụ',
        'selectedService.exists' => 'Dịch vụ không hợp lệ',
        'link.required' => 'Vui lòng nhập link',
        'link.url' => 'Link không hợp lệ',
        'link.max' => 'Link quá dài (tối đa 500 ký tự)',
        'quantity.required' => 'Vui lòng nhập số lượng',
        'quantity.integer' => 'Số lượng phải là số nguyên',
        'quantity.min' => 'Số lượng phải lớn hơn 0'
    ];

    public function mount()
    {
        $this->loadServices();
        $this->loadCategories();
    }

    public function loadServices()
    {
        $query = ApiService::where('is_active', true);

        if ($this->selectedCategory) {
            $query->where('category', $this->selectedCategory);
        }

        if ($this->searchTerm) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->searchTerm . '%')
                  ->orWhere('category', 'like', '%' . $this->searchTerm . '%');
            });
        }

        $this->services = $query->orderBy('category')
                               ->orderBy('name')
                               ->get()
                               ->groupBy('category')
                               ->toArray();
    }

    public function loadCategories()
    {
        $this->categories = ApiService::where('is_active', true)
                                    ->distinct()
                                    ->pluck('category')
                                    ->sort()
                                    ->values()
                                    ->toArray();
    }

    public function updatedSelectedCategory()
    {
        $this->loadServices();
        $this->selectedService = null;
    }

    public function updatedSearchTerm()
    {
        $this->loadServices();
        $this->selectedService = null;
    }

    public function updatedSelectedService()
    {
        if ($this->selectedService) {
            $service = ApiService::find($this->selectedService);
            if ($service) {
                // Auto-adjust quantity to min/max limits
                if ($this->quantity < $service->min_quantity) {
                    $this->quantity = $service->min_quantity;
                }
                if ($this->quantity > $service->max_quantity) {
                    $this->quantity = $service->max_quantity;
                }
            }
        }
    }

    public function calculatePrice()
    {
        if (!$this->selectedService || !$this->quantity) {
            return 0;
        }

        $service = ApiService::find($this->selectedService);
        if (!$service) {
            return 0;
        }

        return $service->final_price * $this->quantity;
    }

    public function getSelectedServiceData()
    {
        if (!$this->selectedService) {
            return null;
        }

        return ApiService::find($this->selectedService);
    }

    public function placeOrder()
    {
        $this->validate();

        try {
            $service = ApiService::find($this->selectedService);
            if (!$service) {
                session()->flash('error', 'Dịch vụ không tồn tại');
                return;
            }

            // Validate quantity limits
            if ($this->quantity < $service->min_quantity || $this->quantity > $service->max_quantity) {
                session()->flash('error', "Số lượng phải từ {$service->min_quantity} đến {$service->max_quantity}");
                return;
            }

            $totalAmount = $this->calculatePrice();
            $user = Auth::user();

            // Create view order
            $viewOrder = ViewOrder::create([
                'user_id' => $user->id,
                'api_service_id' => $service->id,
                'link' => $this->link,
                'quantity' => $this->quantity,
                'total_amount' => $totalAmount,
                'status' => 'PENDING'
            ]);

            // Generate payment code
            $paymentCode = 'VIEW' . str_pad($viewOrder->id, 6, '0', STR_PAD_LEFT);

            // Create transaction
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'view_order_id' => $viewOrder->id,
                'payment_code' => $paymentCode,
                'amount' => $totalAmount,
                'currency' => 'USD',
                'payment_gateway' => 'VIETQR_VCB',
                'status' => 'PENDING',
                'description' => "Mua {$this->quantity} {$service->name}"
            ]);

            Log::info('View order created', [
                'order_id' => $viewOrder->id,
                'user_id' => $user->id,
                'service' => $service->name,
                'quantity' => $this->quantity,
                'amount' => $totalAmount
            ]);

            // Reset form
            $this->reset(['selectedService', 'link', 'quantity']);
            $this->quantity = 100;

            session()->flash('success', 'Đơn hàng đã được tạo thành công! Mã thanh toán: ' . $paymentCode);

            // Redirect to payment page
            return redirect()->route('payment.view-order', ['order' => $viewOrder->id]);

        } catch (\Exception $e) {
            Log::error('Error creating view order', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            session()->flash('error', 'Có lỗi xảy ra khi tạo đơn hàng. Vui lòng thử lại.');
        }
    }

    public function render()
    {
        return view('livewire.view-service-manager', [
            'selectedServiceData' => $this->getSelectedServiceData(),
            'calculatedPrice' => $this->calculatePrice()
        ]);
    }
}
