<?php

namespace App\Livewire\User;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use App\Models\MmoService;
use App\Models\MmoOrder;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

#[Layout('layouts.sidebar')]
class MmoServices extends Component
{
    use WithPagination;

    public $search = '';
    public $categoryFilter = '';
    public $priceFilter = '';

    // Order modal
    public $showOrderModal = false;
    public $selectedServiceId = '';
    public $customerRequirements = '';
    public $orderQuantity = 1;

    protected $rules = [
        'customerRequirements' => 'required|string|max:1000',
        'orderQuantity' => 'required|integer|min:1|max:100'
    ];

    public function openOrderModal($serviceId)
    {
        $this->selectedServiceId = $serviceId;
        $this->customerRequirements = '';
        $this->orderQuantity = 1;
        $this->showOrderModal = true;
    }

    public function closeOrderModal()
    {
        $this->showOrderModal = false;
        $this->reset(['selectedServiceId', 'customerRequirements', 'orderQuantity']);
    }

    public function placeOrder()
    {
        $this->validate();

        try {
            $service = MmoService::findOrFail($this->selectedServiceId);
            $user = auth()->user();
            $totalAmount = $service->price * $this->orderQuantity;

            // Check balance
            if ($user->balance < $totalAmount) {
                session()->flash('error', 'Số dư không đủ. Vui lòng nạp thêm tiền.');
                return;
            }

            $order = null;

            DB::transaction(function () use ($service, $user, $totalAmount, &$order) {
                // Deduct balance
                $user->decrement('balance', $totalAmount);

                // Create transaction
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'amount' => $totalAmount,
                    'currency' => 'USD',
                    'payment_gateway' => 'BALANCE_DEDUCTION',
                    'status' => 'COMPLETED',
                    'description' => "MMO Service: {$service->name} x{$this->orderQuantity}",
                    'payment_code' => 'MMO' . time(),
                    'api_response' => [
                        'mmo_service_id' => $service->id,
                        'quantity' => $this->orderQuantity,
                        'unit_price' => $service->price,
                        'total_amount' => $totalAmount
                    ]
                ]);

                // Create MMO order
                $order = MmoOrder::create([
                    'user_id' => $user->id,
                    'mmo_service_id' => $service->id,
                    'transaction_id' => $transaction->id,
                    'order_code' => 'MMO' . str_pad($service->id, 3, '0', STR_PAD_LEFT) . time(),
                    'amount' => $totalAmount,
                    'currency' => 'USD',
                    'status' => 'PENDING',
                    'customer_requirements' => [
                        'requirements' => $this->customerRequirements,
                        'quantity' => $this->orderQuantity,
                        'ordered_at' => now()->toISOString()
                    ]
                ]);

                Log::info('MMO Order placed', [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'amount' => $totalAmount
                ]);
            });

            // Send Telegram notification after transaction
            if ($order) {
                $this->sendTelegramNotification($order, $service, $user);
            }

            session()->flash('success', "✅ Đã đặt hàng thành công! Mã đơn: #{$order->order_code}");
            $this->closeOrderModal();

        } catch (\Exception $e) {
            Log::error('MMO Order failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'service_id' => $this->selectedServiceId
            ]);
            session()->flash('error', 'Có lỗi xảy ra khi đặt hàng. Vui lòng thử lại.');
        }
    }

    private function sendTelegramNotification($order, $service, $user)
    {
        try {
            $botToken = config('services.telegram.bot_token');
            $chatId = config('services.telegram.admin_chat_id');

            if (!$botToken || !$chatId) {
                return;
            }

            $message = "🎮 *New MMO Order!*\n\n";
            $message .= "📋 Order: `#{$order->order_code}`\n";
            $message .= "👤 Customer: {$user->name}\n";
            $message .= "📧 Email: {$user->email}\n";
            $message .= "🛍️ Service: *{$service->name}*\n";
            $message .= "💰 Amount: \${$order->amount}\n";
            $message .= "📦 Quantity: {$order->customer_requirements['quantity']}\n";
            $message .= "📝 Requirements:\n`{$order->customer_requirements['requirements']}`\n";
            $message .= "⏰ Delivery: {$service->delivery_time}\n";
            $message .= "\n🕒 " . now()->format('d/m/Y H:i:s');
            $message .= "\n\n🔗 [Manage Orders](/admin/mmo-orders)";

            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);

        } catch (\Exception $e) {
            Log::error('Telegram notification failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id
            ]);
        }
    }

    public function render()
    {
        $query = MmoService::active()
                          ->when($this->search, function($q) {
                              $q->where('name', 'like', '%' . $this->search . '%')
                                ->orWhere('description', 'like', '%' . $this->search . '%')
                                ->orWhere('category', 'like', '%' . $this->search . '%');
                          })
                          ->when($this->categoryFilter, function($q) {
                              $q->where('category', $this->categoryFilter);
                          })
                          ->when($this->priceFilter, function($q) {
                              if ($this->priceFilter === 'low') {
                                  $q->where('price', '<=', 10);
                              } elseif ($this->priceFilter === 'medium') {
                                  $q->whereBetween('price', [10.01, 50]);
                              } elseif ($this->priceFilter === 'high') {
                                  $q->where('price', '>', 50);
                              }
                          })
                          ->orderBy('is_featured', 'desc')
                          ->orderBy('sort_order')
                          ->orderBy('created_at', 'desc');

        $services = $query->paginate(12);

        // Categories for filter
        $categories = MmoService::active()
                                ->select('category')
                                ->distinct()
                                ->pluck('category')
                                ->filter()
                                ->sort()
                                ->values();

        // Featured services
        $featuredServices = MmoService::active()
                                     ->featured()
                                     ->orderBy('sort_order')
                                     ->limit(6)
                                     ->get();

        $selectedService = $this->selectedServiceId
            ? MmoService::find($this->selectedServiceId)
            : null;

        return view('livewire.user.mmo-services', [
            'services' => $services,
            'categories' => $categories,
            'featuredServices' => $featuredServices,
            'selectedService' => $selectedService
        ]);
    }
}
