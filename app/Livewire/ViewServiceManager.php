<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\ApiService;
use App\Models\ViewOrder;
use App\Models\Transaction;
use App\Models\ScheduledOrder;
use App\Jobs\ProcessViewOrderJob;
use App\Services\JapApiService;
use App\Services\TelegramNotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

#[Layout('layouts.sidebar')]
class ViewServiceManager extends Component
{
    public $selectedService = null;
    public $selectedServiceData = null;
    public $link = '';
    public $quantity = 100;
    public $services = [];
    public $youtubeCategories = [];
    public $selectedCategory = '';
    public $selectedSubCategory = '';
    public $searchTerm = '';
    public $subCategories = [];
    public $categoryServices = [];

    // Scheduled Order Features
    public $enableScheduledOrder = false;
    public $scheduledDateTime = '';
    public $enableRepeatOrder = false;
    public $repeatInterval = 24; // hours
    public $maxRepeats = 1;

    private $japApiService;

    protected function getJapApiService()
    {
        if (!$this->japApiService) {
            $this->japApiService = app(JapApiService::class);
        }
        return $this->japApiService;
    }

    protected $rules = [
        'selectedService' => 'required',
        'link' => 'required|url|max:500',
        'quantity' => 'required|integer|min:1',
        'scheduledDateTime' => 'nullable|date|after:now',
        'repeatInterval' => 'nullable|integer|min:1|max:168',
        'maxRepeats' => 'nullable|integer|min:1|max:100'
    ];

    protected $messages = [
        'selectedService.required' => 'Vui lòng chọn dịch vụ',
        'link.required' => 'Vui lòng nhập link YouTube',
        'link.url' => 'Link không hợp lệ',
        'link.max' => 'Link quá dài (tối đa 500 ký tự)',
        'quantity.required' => 'Vui lòng nhập số lượng',
        'quantity.integer' => 'Số lượng phải là số nguyên',
        'quantity.min' => 'Số lượng tối thiểu là 1',
        'scheduledDateTime.after' => 'Thời gian hẹn phải sau thời điểm hiện tại',
        'repeatInterval.min' => 'Khoảng cách lặp lại tối thiểu 1 giờ',
        'repeatInterval.max' => 'Khoảng cách lặp lại tối đa 168 giờ (7 ngày)',
        'maxRepeats.min' => 'Số lần lặp tối thiểu là 1',
        'maxRepeats.max' => 'Số lần lặp tối đa là 100'
    ];

    public function mount()
    {
        $this->japApiService = app(JapApiService::class);
        $this->loadYouTubeCategories();
    }

    public function boot()
    {
        if (!$this->japApiService) {
            $this->japApiService = app(JapApiService::class);
        }
    }

    public function hydrate()
    {
        if (!$this->japApiService) {
            $this->japApiService = app(JapApiService::class);
        }
    }

    public function loadYouTubeCategories()
    {
        $japApiService = $this->getJapApiService();
        $this->youtubeCategories = $japApiService->getYouTubeServices();
    }

    public function selectCategory($category)
    {
        $this->selectedCategory = $category;
        $this->selectedSubCategory = '';
        $this->subCategories = array_keys($this->youtubeCategories[$category] ?? []);
        $this->categoryServices = [];
        $this->selectedService = null;
    }

    public function selectSubCategory($subCategory)
    {
        $this->selectedSubCategory = $subCategory;
        $this->categoryServices = $this->youtubeCategories[$this->selectedCategory][$subCategory] ?? [];
        $this->selectedService = null;
    }

    public function selectService($serviceData)
    {
        // If serviceData is JSON string, decode it
        if (is_string($serviceData)) {
            $serviceData = json_decode($serviceData, true);
        }

        // Store both JSON string for dropdown and decoded data for processing
        $this->selectedService = json_encode($serviceData);
        $this->selectedServiceData = $serviceData;

        // Set quantity limits based on service
        if (isset($serviceData['min'])) {
            $this->quantity = max($this->quantity, (int) $serviceData['min']);
        }
    }

    public function getCategoryStats()
    {
        $stats = [];
        foreach ($this->youtubeCategories as $category => $subCategories) {
            $totalServices = 0;
            $allPrices = [];

            foreach ($subCategories as $subCategory => $services) {
                $totalServices += count($services);
                foreach ($services as $service) {
                    $allPrices[] = (float) $service['rate'];
                }
            }

            $stats[$category] = [
                'count' => $totalServices,
                'sub_count' => count($subCategories),
                'min_price' => !empty($allPrices) ? min($allPrices) : 0,
                'max_price' => !empty($allPrices) ? max($allPrices) : 0
            ];
        }
        return $stats;
    }

    public function getSubCategoryStats()
    {
        if (!$this->selectedCategory || !isset($this->youtubeCategories[$this->selectedCategory])) {
            return [];
        }

        $stats = [];
        foreach ($this->youtubeCategories[$this->selectedCategory] as $subCategory => $services) {
            $prices = array_map(function($service) {
                return (float) $service['rate'];
            }, $services);

            $stats[$subCategory] = [
                'count' => count($services),
                'min_price' => !empty($prices) ? min($prices) : 0,
                'max_price' => !empty($prices) ? max($prices) : 0
            ];
        }
        return $stats;
    }

    public function updatedSelectedCategory()
    {
        $this->selectedService = null;
        $this->selectedSubCategory = '';
        $this->subCategories = array_keys($this->youtubeCategories[$this->selectedCategory] ?? []);
        $this->categoryServices = [];
    }

    public function updatedSelectedSubCategory()
    {
        $this->selectedService = null;
        $this->selectedServiceData = null;
        $this->categoryServices = $this->youtubeCategories[$this->selectedCategory][$this->selectedSubCategory] ?? [];
    }

    public function updatedSelectedService()
    {
        // If selectedService is JSON string, decode it but keep original for dropdown
        if (is_string($this->selectedService) && !empty($this->selectedService)) {
            $decodedService = json_decode($this->selectedService, true);
            if ($decodedService) {
                $this->selectedServiceData = $decodedService;

                // Set quantity limits based on service
                if (isset($decodedService['min'])) {
                    $this->quantity = max($this->quantity, (int) $decodedService['min']);
                }
            }
        } else {
            $this->selectedServiceData = null;
        }
    }

    public function updatedQuantity()
    {
        if ($this->selectedServiceData && isset($this->selectedServiceData['min']) && isset($this->selectedServiceData['max'])) {
            $min = (int) $this->selectedServiceData['min'];
            $max = (int) $this->selectedServiceData['max'];

            if ($this->quantity < $min) {
                $this->quantity = $min;
            }
            if ($this->quantity > $max) {
                $this->quantity = $max;
            }
        }
    }

    public function calculatePrice()
    {
        if (!$this->selectedServiceData || !$this->quantity) {
            return 0;
        }

        // JAP API rate is per 1000 units
        $ratePer1000 = (float) $this->selectedServiceData['rate'];
        $markup = 1.2; // 20% markup

        // Calculate price: (rate per 1000) × (quantity / 1000) × markup
        return ($ratePer1000 * $this->quantity / 1000) * $markup;
    }

    public function getRecentOrdersProperty()
    {
        return ViewOrder::with('user')
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }



    public function placeOrder()
    {
        Log::info('placeOrder called', [
            'user_id' => Auth::id(),
            'selectedService' => $this->selectedService,
            'japApiService_exists' => !is_null($this->japApiService)
        ]);

        $this->validate();

        try {
            if (!$this->selectedServiceData) {
                session()->flash('error', 'Vui lòng chọn dịch vụ');
                return;
            }

            // Validate quantity limits
            $min = (int) $this->selectedServiceData['min'];
            $max = (int) $this->selectedServiceData['max'];

            if ($this->quantity < $min || $this->quantity > $max) {
                session()->flash('error', "Số lượng phải từ {$min} đến {$max}");
                return;
            }

            $totalAmount = $this->calculatePrice();
            $user = Auth::user();

            // Check user balance first
            if ($user->balance < $totalAmount) {
                session()->flash('error', "Số dư không đủ. Cần: $" . number_format($totalAmount, 2) . ", Có: $" . number_format($user->balance, 2));
                return;
            }

            // Try to place order via JAP API first
            try {
                $japApiService = $this->getJapApiService();
                if (!$japApiService) {
                    throw new \Exception('JAP API Service not available');
                }

                $japResult = $japApiService->placeOrder(
                    $this->selectedServiceData['service'],
                    $this->link,
                    $this->quantity
                );
            } catch (\Exception $apiException) {
                Log::error('JAP API Service error', [
                    'error' => $apiException->getMessage(),
                    'user_id' => Auth::id()
                ]);

                // Fallback: create order as PENDING_RETRY
                $japResult = null;
            }

            // Create view order with appropriate status
            $orderStatus = 'PENDING';
            $apiResponse = [];

            if ($japResult && isset($japResult['order'])) {
                // Order successful - API accepted the order
                $orderStatus = 'PROCESSING';
                $apiResponse = [
                    'api_order_id' => $japResult['order'],
                    'placed_at' => now()->toISOString()
                ];

                Log::info('Order placed successfully via JAP API', [
                    'order_api_id' => $japResult['order'],
                    'user_id' => $user->id,
                    'service_id' => $this->selectedServiceData['service']
                ]);

            } elseif ($japResult && isset($japResult['error'])) {
                // Order failed - check if it's insufficient funds
                $error = $japResult['error'];
                if (stripos($error, 'not enough funds') !== false ||
                    stripos($error, 'insufficient funds') !== false ||
                    stripos($error, 'low balance') !== false) {

                    // Provider is out of funds - this is a system issue, not user issue
                    $orderStatus = 'PENDING_FUNDS';
                    $apiResponse = [
                        'error' => $error,
                        'failed_at' => now()->toISOString(),
                        'requires_admin_action' => true
                    ];

                    Log::warning('JAP API insufficient funds detected', [
                        'error' => $error,
                        'user_id' => $user->id,
                        'service_id' => $this->selectedServiceData['service'],
                        'amount' => $totalAmount
                    ]);

                } else {
                    // Other API errors
                    $orderStatus = 'FAILED';
                    $apiResponse = [
                        'error' => $error,
                        'failed_at' => now()->toISOString()
                    ];
                }
            } else {
                // API call failed completely
                $orderStatus = 'PENDING_RETRY';
                $apiResponse = [
                    'error' => 'API call failed - no response',
                    'failed_at' => now()->toISOString()
                ];
            }

            $viewOrder = ViewOrder::create([
                'user_id' => $user->id,
                'service_id' => $this->selectedServiceData['service'],
                'api_order_id' => $apiResponse['api_order_id'] ?? null,
                'link' => $this->link,
                'quantity' => $this->quantity,
                'total_amount' => $totalAmount,
                'status' => $orderStatus,
                'api_response' => $apiResponse
            ]);

            // Always deduct balance for accepted orders (we take responsibility)
            // Only don't deduct for complete API failures
            if (!in_array($orderStatus, ['FAILED'])) {
                $user->decrement('balance', $totalAmount);

                Log::info('Balance deducted for view order', [
                    'user_id' => $user->id,
                    'order_id' => $viewOrder->id,
                    'amount_deducted' => $totalAmount,
                    'remaining_balance' => $user->fresh()->balance,
                    'order_status' => $orderStatus
                ]);
            }

            // Send Telegram notification for insufficient funds
            if ($orderStatus === 'PENDING_FUNDS') {
                try {
                    $telegramService = app(TelegramNotificationService::class);
                    $telegramService->notifyInsufficientFunds($viewOrder, $apiResponse['error']);
                } catch (\Exception $e) {
                    Log::error('Failed to send Telegram notification', [
                        'error' => $e->getMessage(),
                        'order_id' => $viewOrder->id
                    ]);
                }
            }

            // Generate payment code
            $paymentCode = 'VIEW' . str_pad($viewOrder->id, 6, '0', STR_PAD_LEFT);

            // Create transaction with appropriate status
            $transactionStatus = 'PENDING';
            $paymentGateway = 'BALANCE_DEDUCTION';

            // Mark as completed if balance was deducted
            if (!in_array($orderStatus, ['FAILED'])) {
                $transactionStatus = 'COMPLETED'; // Balance already deducted
            }

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'view_order_id' => $viewOrder->id,
                'payment_code' => $paymentCode,
                'amount' => $totalAmount,
                'currency' => 'USD',
                'payment_gateway' => $paymentGateway,
                'status' => $transactionStatus,
                'description' => "Mua {$this->quantity} {$this->selectedServiceData['name']}"
            ]);

            Log::info('View order created', [
                'order_id' => $viewOrder->id,
                'user_id' => $user->id,
                'service' => $this->selectedService['name'],
                'quantity' => $this->quantity,
                'amount' => $totalAmount
            ]);

            // Show appropriate message based on order status
            if ($orderStatus === 'PROCESSING') {
                session()->flash('success', "✅ Đơn hàng #{$viewOrder->id} đã được đặt thành công! Đã trừ $" . number_format($totalAmount, 2) . " từ số dư. Đang xử lý...");
            } elseif ($orderStatus === 'PENDING') {
                session()->flash('success', "✅ Đơn hàng #{$viewOrder->id} đã được tạo! Đã trừ $" . number_format($totalAmount, 2) . " từ số dư. Đang chờ xử lý...");
            } elseif ($orderStatus === 'PENDING_FUNDS') {
                session()->flash('warning', "⏳ Đơn hàng #{$viewOrder->id} đã được tiếp nhận! Hệ thống đang quá tải, vui lòng chờ xử lý. Đã trừ $" . number_format($totalAmount, 2) . " từ số dư. Bạn có thể hủy đơn nếu cần.");
            } elseif ($orderStatus === 'PENDING_RETRY') {
                session()->flash('info', "📋 Đơn hàng #{$viewOrder->id} đã được tiếp nhận! Hệ thống đang xử lý, vui lòng chờ. Đã trừ $" . number_format($totalAmount, 2) . " từ số dư.");
            } elseif ($orderStatus === 'FAILED') {
                session()->flash('error', "❌ Đơn hàng #{$viewOrder->id} thất bại: " . ($apiResponse['error'] ?? 'Lỗi không xác định') . ". Chưa trừ tiền.");
            } else {
                session()->flash('info', "📋 Đơn hàng #{$viewOrder->id} đã được tạo. Đã trừ $" . number_format($totalAmount, 2) . " từ số dư.");
            }

            // Reset form for all accepted orders (money deducted)
            if (!in_array($orderStatus, ['FAILED'])) {
                $this->reset(['selectedService', 'selectedServiceData', 'link', 'quantity']);
                $this->quantity = 100;
            }

        } catch (\Exception $e) {
            Log::error('Error creating view order', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            session()->flash('error', 'Có lỗi xảy ra khi tạo đơn hàng. Vui lòng thử lại.');
        }
    }

    public function placeScheduledOrder()
    {
        $this->validate();

        try {
            if (!$this->selectedServiceData) {
                session()->flash('error', 'Vui lòng chọn dịch vụ');
                return;
            }

            $totalAmount = $this->calculatePrice();
            $user = Auth::user();

            // Validate scheduled time
            if ($this->enableScheduledOrder && empty($this->scheduledDateTime)) {
                session()->flash('error', 'Vui lòng chọn thời gian hẹn');
                return;
            }

            $scheduledAt = $this->enableScheduledOrder
                ? \Carbon\Carbon::parse($this->scheduledDateTime)
                : now();

            // Calculate total cost for repeat orders
            $totalCost = $totalAmount;
            if ($this->enableRepeatOrder) {
                $totalCost = $totalAmount * $this->maxRepeats;
            }

            // Check balance for total cost
            if ($user->balance < $totalCost) {
                session()->flash('error', "Số dư không đủ. Cần: $" . number_format($totalCost, 2) . ", Có: $" . number_format($user->balance, 2));
                return;
            }

            $scheduledOrder = ScheduledOrder::create([
                'user_id' => $user->id,
                'service_id' => $this->selectedServiceData['service'],
                'link' => $this->link,
                'quantity' => $this->quantity,
                'total_amount' => $totalAmount,
                'scheduled_at' => $scheduledAt,
                'is_repeat' => $this->enableRepeatOrder,
                'repeat_interval_hours' => $this->enableRepeatOrder ? $this->repeatInterval : null,
                'max_repeats' => $this->enableRepeatOrder ? $this->maxRepeats : 1,
                'service_data' => $this->selectedServiceData,
                'next_execution_at' => $scheduledAt
            ]);

            // Reserve balance for scheduled orders
            if ($this->enableScheduledOrder || $this->enableRepeatOrder) {
                $user->decrement('balance', $totalCost);

                Log::info('Balance reserved for scheduled order', [
                    'user_id' => $user->id,
                    'scheduled_order_id' => $scheduledOrder->id,
                    'reserved_amount' => $totalCost,
                    'remaining_balance' => $user->fresh()->balance
                ]);
            }

            $message = "✅ Đơn hàng hẹn giờ #{$scheduledOrder->id} đã được tạo thành công!";

            if ($this->enableScheduledOrder) {
                $message .= " Sẽ thực hiện lúc: " . $scheduledAt->format('d/m/Y H:i');
            }

            if ($this->enableRepeatOrder) {
                $message .= " Lặp lại {$this->maxRepeats} lần, mỗi {$this->repeatInterval} giờ.";
            }

            session()->flash('success', $message);

            // Reset form
            $this->reset(['selectedService', 'selectedServiceData', 'link', 'quantity', 'enableScheduledOrder', 'scheduledDateTime', 'enableRepeatOrder']);
            $this->quantity = 100;
            $this->repeatInterval = 24;
            $this->maxRepeats = 1;

        } catch (\Exception $e) {
            Log::error('Error creating scheduled order', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            session()->flash('error', 'Có lỗi xảy ra khi tạo đơn hàng hẹn giờ. Vui lòng thử lại.');
        }
    }

    public function cancelOrder($orderId)
    {
        try {
            $order = ViewOrder::where('id', $orderId)
                             ->where('user_id', Auth::id())
                             ->first();

            if (!$order) {
                session()->flash('error', 'Đơn hàng không tồn tại hoặc không thuộc về bạn');
                return;
            }

            if (!$order->canCancel()) {
                session()->flash('error', 'Đơn hàng này không thể hủy');
                return;
            }

            $canRefund = false;
            $cancelReason = 'Cancelled by user';
            $japCancelSuccess = false;

            // Check if order was sent to JAP API
            if ($order->api_order_id) {
                // First, check current order status on JAP
                try {
                    $japApiService = $this->getJapApiService();
                    $statusResult = $japApiService->getOrderStatus($order->api_order_id);

                    if ($statusResult && isset($statusResult['status'])) {
                        $japStatus = $statusResult['status'];

                        Log::info('JAP order status check', [
                            'order_id' => $order->id,
                            'api_order_id' => $order->api_order_id,
                            'jap_status' => $japStatus
                        ]);

                        // Try to cancel via JAP API
                        $cancelResult = $japApiService->cancelOrders($order->api_order_id);

                        if ($cancelResult && !isset($cancelResult['error'])) {
                            // JAP cho phép hủy → Hoàn tiền
                            $canRefund = true;
                            $cancelReason = 'JAP approved cancellation';

                            Log::info('Order cancelled successfully via JAP API', [
                                'order_id' => $order->id,
                                'api_order_id' => $order->api_order_id,
                                'jap_status' => $japStatus
                            ]);
                        } else {
                            // JAP không cho phép hủy → Không hoàn tiền
                            $canRefund = false;
                            $cancelReason = 'JAP declined cancellation';

                            Log::warning('JAP API cancel declined', [
                                'order_id' => $order->id,
                                'api_order_id' => $order->api_order_id,
                                'jap_status' => $japStatus,
                                'cancel_result' => $cancelResult
                            ]);
                        }
                    } else {
                        // Không lấy được status → Không hoàn tiền (an toàn)
                        $canRefund = false;
                        $cancelReason = 'Cannot verify JAP order status';
                    }

                } catch (\Exception $e) {
                    Log::error('JAP API error during cancel', [
                        'order_id' => $order->id,
                        'api_order_id' => $order->api_order_id,
                        'error' => $e->getMessage()
                    ]);

                    $canRefund = false;
                    $cancelReason = 'JAP API error';
                }
            } else {
                // Đơn chưa gửi JAP → An toàn hoàn tiền
                $canRefund = true;
                $cancelReason = 'Order not sent to JAP yet';
            }

            // Process based on refund eligibility
            if ($canRefund) {
                // JAP cho phép hủy → Hoàn tiền
                if ($order->processRefund($cancelReason)) {
                    session()->flash('success', "✅ Đơn hàng #{$order->id} đã được hủy và hoàn tiền $" . number_format($order->total_amount, 2));
                } else {
                    session()->flash('error', "❌ Không thể xử lý hoàn tiền cho đơn hàng #{$order->id}");
                }
            } else {
                // JAP không cho phép hủy → Không hoàn tiền
                $order->update([
                    'status' => 'CANCELLED',
                    'api_response' => array_merge($order->api_response ?? [], [
                        'cancel_requested_at' => now()->toISOString(),
                        'cancel_reason' => $cancelReason,
                        'refund_denied' => true
                    ])
                ]);

                session()->flash('warning', "⚠️ Đơn hàng #{$order->id} không thể hủy (đã được xử lý). Vui lòng liên hệ admin để được hỗ trợ.");

                // Notify admin about manual review needed
                try {
                    $telegramService = app(TelegramNotificationService::class);
                    $telegramService->notifyOrderIssue($order, $cancelReason, 'Manual review required for refund');
                } catch (\Exception $e) {
                    Log::error('Failed to send Telegram notification for cancel issue', [
                        'error' => $e->getMessage(),
                        'order_id' => $order->id
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error('Cancel order failed', [
                'order_id' => $orderId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            session()->flash('error', "❌ Lỗi khi hủy đơn hàng");
        }
    }

    public function requestRefill($orderId)
    {
        try {
            $order = ViewOrder::where('id', $orderId)
                             ->where('user_id', Auth::id())
                             ->first();

            if (!$order) {
                session()->flash('error', 'Đơn hàng không tồn tại hoặc không thuộc về bạn');
                return;
            }

            if (!$order->api_order_id) {
                session()->flash('error', 'Đơn hàng chưa có API Order ID, không thể bảo hành');
                return;
            }

            if ($order->status !== 'COMPLETED') {
                session()->flash('error', 'Chỉ có thể bảo hành đơn hàng đã hoàn thành');
                return;
            }

            // Check if already has refill
            $apiResponse = $order->api_response ?? [];
            if (isset($apiResponse['refill_id'])) {
                session()->flash('warning', 'Đơn hàng này đã được yêu cầu bảo hành rồi');
                return;
            }

            // Request refill via JAP API
            $japApiService = $this->getJapApiService();
            $result = $japApiService->createRefill($order->api_order_id);

            if ($result && isset($result['refill'])) {
                $refillId = $result['refill'];

                // Update order with refill info
                $order->update([
                    'api_response' => array_merge($apiResponse, [
                        'refill_id' => $refillId,
                        'refill_requested_at' => now()->toISOString()
                    ])
                ]);

                session()->flash('success', "✅ Đã gửi yêu cầu bảo hành cho đơn hàng #{$order->id}. Refill ID: {$refillId}");

                Log::info('Refill requested successfully', [
                    'order_id' => $order->id,
                    'api_order_id' => $order->api_order_id,
                    'refill_id' => $refillId
                ]);

            } else {
                session()->flash('error', "❌ Không thể tạo yêu cầu bảo hành cho đơn hàng #{$order->id}");

                Log::error('Refill request failed', [
                    'order_id' => $order->id,
                    'api_order_id' => $order->api_order_id,
                    'result' => $result
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Request refill failed', [
                'order_id' => $orderId,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            session()->flash('error', "❌ Lỗi khi yêu cầu bảo hành");
        }
    }

    public function render()
    {
        return view('livewire.view-service-manager', [
            'youtubeCategories' => $this->youtubeCategories,
            'categoryStats' => $this->getCategoryStats(),
            'subCategories' => $this->subCategories,
            'subCategoryStats' => $this->getSubCategoryStats(),
            'categoryServices' => $this->categoryServices,
            'selectedServiceData' => $this->selectedServiceData,
            'calculatedPrice' => $this->calculatePrice(),
            'recentOrders' => $this->recentOrders
        ]);
    }
}
