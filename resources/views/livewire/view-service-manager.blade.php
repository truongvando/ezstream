<div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
    <div class="flex items-center mb-6">
        <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-lg mr-4">
            <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
        </div>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">🛒 Mua View & Tương tác</h2>
            <p class="text-gray-600 dark:text-gray-400">Tăng view, like, subscriber cho các platform social media</p>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tìm kiếm dịch vụ</label>
            <input wire:model.live="searchTerm" type="text" 
                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" 
                   placeholder="Nhập tên dịch vụ...">
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Lọc theo danh mục</label>
            <select wire:model.live="selectedCategory" 
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                <option value="">-- Tất cả danh mục --</option>
                @foreach($categories as $category)
                    <option value="{{ $category }}">{{ $category }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Service Selection -->
    <div class="mb-6">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Chọn Dịch Vụ</label>
        <select wire:model.live="selectedService" 
                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
            <option value="">-- Chọn dịch vụ --</option>
            @foreach($services as $category => $categoryServices)
                <optgroup label="{{ $category }}">
                    @foreach($categoryServices as $service)
                        <option value="{{ $service->id }}">
                            {{ $service->name }} - ${{ number_format($service->final_price, 3) }} 
                            ({{ number_format($service->min_quantity) }}-{{ number_format($service->max_quantity) }})
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error('selectedService') 
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p> 
        @enderror
    </div>

    <!-- Service Details -->
    @if($selectedServiceData)
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">Chi tiết dịch vụ</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-gray-600 dark:text-gray-400">Giá:</span>
                    <span class="font-medium text-gray-900 dark:text-white">${{ number_format($selectedServiceData->final_price, 3) }}</span>
                </div>
                <div>
                    <span class="text-gray-600 dark:text-gray-400">Số lượng tối thiểu:</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ number_format($selectedServiceData->min_quantity) }}</span>
                </div>
                <div>
                    <span class="text-gray-600 dark:text-gray-400">Số lượng tối đa:</span>
                    <span class="font-medium text-gray-900 dark:text-white">{{ number_format($selectedServiceData->max_quantity) }}</span>
                </div>
                <div>
                    <span class="text-gray-600 dark:text-gray-400">Refill:</span>
                    <span class="font-medium {{ $selectedServiceData->refill ? 'text-green-600' : 'text-red-600' }}">
                        {{ $selectedServiceData->refill ? 'Có' : 'Không' }}
                    </span>
                </div>
            </div>
        </div>
    @endif

    <!-- Order Form -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Link</label>
            <input wire:model="link" type="url" 
                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" 
                   placeholder="https://example.com/your-content">
            @error('link') 
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p> 
            @enderror
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Số lượng</label>
            <input wire:model.live="quantity" type="number" 
                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" 
                   min="1" 
                   @if($selectedServiceData) 
                       min="{{ $selectedServiceData->min_quantity }}" 
                       max="{{ $selectedServiceData->max_quantity }}"
                   @endif>
            @error('quantity') 
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p> 
            @enderror
        </div>
    </div>

    <!-- Price Display -->
    @if($calculatedPrice > 0)
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold text-green-900 dark:text-green-100">Tổng tiền</h3>
                    <p class="text-sm text-green-700 dark:text-green-300">
                        {{ number_format($quantity) }} × ${{ number_format($selectedServiceData->final_price ?? 0, 3) }}
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-green-900 dark:text-green-100">
                        ${{ number_format($calculatedPrice, 2) }}
                    </div>
                    <div class="text-sm text-green-700 dark:text-green-300">
                        ≈ {{ number_format($calculatedPrice * 24000) }} VND
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Order Button -->
    <div class="flex justify-end">
        <button wire:click="placeOrder" 
                @if(!$selectedService || !$link || !$quantity) disabled @endif
                class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed text-white font-medium px-8 py-3 rounded-lg transition-colors">
            <span wire:loading.remove wire:target="placeOrder">
                🛒 Đặt hàng ngay
            </span>
            <span wire:loading wire:target="placeOrder">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Đang xử lý...
            </span>
        </button>
    </div>

    <!-- Recent Orders -->
    <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">📋 Đơn hàng gần đây</h3>
        @php
            $recentOrders = auth()->user()->viewOrders()->with('apiService')->latest()->take(5)->get();
        @endphp
        
        @if($recentOrders->count() > 0)
            <div class="space-y-3">
                @foreach($recentOrders as $order)
                    <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">{{ $order->apiService->name }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                {{ number_format($order->quantity) }} × ${{ number_format($order->total_amount / $order->quantity, 3) }}
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="font-medium text-gray-900 dark:text-white">${{ number_format($order->total_amount, 2) }}</div>
                            <div class="text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    @if($order->status === 'COMPLETED') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                    @elseif($order->status === 'PROCESSING') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                                    @elseif($order->status === 'PENDING') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                    @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                    @endif">
                                    {{ $order->status }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-gray-500 dark:text-gray-400 text-center py-4">Chưa có đơn hàng nào</p>
        @endif
    </div>
</div>
