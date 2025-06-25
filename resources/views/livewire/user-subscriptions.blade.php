<div>
    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p>{{ session('success') }}</p>
        </div>
    @endif
    
    @if (session()->has('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p>{{ session('error') }}</p>
        </div>
    @endif

    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Gói Đã Mua</h1>
                <p class="mt-1 text-sm text-gray-600">Quản lý các gói dịch vụ bạn đã đăng ký</p>
            </div>
            @php
                $hasActiveSubscription = auth()->user()->subscriptions()->where('status', 'ACTIVE')->exists();
            @endphp
            <a href="{{ route('packages') }}" 
               class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors duration-200">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                {{ $hasActiveSubscription ? 'Nâng Cấp Gói' : 'Mua Gói Mới' }}
            </a>
        </div>
    </div>

    <!-- Subscriptions Grid -->
    @if($subscriptions->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach ($subscriptions as $subscription)
                <div class="bg-white rounded-lg shadow p-6">
                    <!-- Status Badge -->
                    <div class="flex items-center justify-between mb-4">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full 
                            @switch($subscription->status)
                                @case('ACTIVE') bg-green-100 text-green-800 @break
                                @case('PENDING_PAYMENT') bg-yellow-100 text-yellow-800 @break
                                @case('INACTIVE') bg-red-100 text-red-800 @break
                                @case('CANCELED') bg-gray-100 text-gray-800 @break
                                @default bg-blue-100 text-blue-800
                            @endswitch
                        ">{{ $subscription->status }}</span>
                        
                        <span class="text-2xl font-bold text-gray-900">
                            {{ number_format($subscription->servicePackage->price_monthly, 0, ',', '.') }} VNĐ
                        </span>
                    </div>

                    <!-- Package Info -->
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            {{ $subscription->servicePackage->name }}
                        </h3>
                        <p class="text-sm text-gray-600 mb-3">
                            {{ $subscription->servicePackage->description }}
                        </p>
                        
                        <!-- Features -->
                        <ul class="space-y-1 text-sm text-gray-600">
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ $subscription->servicePackage->max_streams }} streams đồng thời
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Chất lượng tối đa: {{ $subscription->servicePackage->max_quality }}
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Lưu trữ: {{ $subscription->servicePackage->storage_limit ? \Illuminate\Support\Number::fileSize($subscription->servicePackage->storage_limit, precision: 1) : 'Không giới hạn' }}
                            </li>
                        </ul>
                    </div>

                    <!-- Dates -->
                    <div class="border-t border-gray-200 pt-4 mb-4">
                        <div class="flex justify-between text-sm text-gray-600">
                            <span>Bắt đầu:</span>
                            <span>{{ $subscription->created_at->format('d/m/Y') }}</span>
                        </div>
                        @if($subscription->ends_at)
                            <div class="flex justify-between text-sm text-gray-600 mt-1">
                                <span>Hết hạn:</span>
                                <span class="{{ $subscription->ends_at->isPast() ? 'text-red-600' : 'text-gray-900' }}">
                                    {{ $subscription->ends_at->format('d/m/Y') }}
                                </span>
                            </div>
                        @endif
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col space-y-2">
                        @if($subscription->status === 'PENDING_PAYMENT')
                            @php
                                $pendingTransaction = $subscription->transactions()->where('status', 'PENDING')->latest()->first();
                            @endphp
                            <div class="flex space-x-2">
                                @if($pendingTransaction)
                                    <a href="{{ route('payment.page', $subscription) }}" 
                                       class="flex-1 text-center px-3 py-2 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-medium rounded-md transition-colors duration-200">
                                        Thanh Toán
                                    </a>
                                @endif
                                <button wire:click="cancelSubscription({{ $subscription->id }})" 
                                        wire:confirm="Bạn có chắc muốn hủy gói này?"
                                        class="px-3 py-2 bg-red-100 hover:bg-red-200 text-red-700 text-sm font-medium rounded-md transition-colors duration-200">
                                    Hủy
                                </button>
                            </div>
                        @elseif($subscription->status === 'ACTIVE')
                            <div class="flex space-x-2">
                                <button class="flex-1 px-3 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md transition-colors duration-200">
                                    Đang Hoạt Động
                                </button>
                                <a href="{{ route('packages') }}" 
                                   class="px-3 py-2 bg-blue-100 hover:bg-blue-200 text-blue-700 text-sm font-medium rounded-md transition-colors duration-200">
                                    Nâng Cấp
                                </a>
                            </div>
                        @endif
                        
                        <button wire:click="showDetails({{ $subscription->id }})" 
                                class="w-full px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-md transition-colors duration-200">
                            Chi Tiết
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Chưa có gói nào</h3>
            <p class="mt-1 text-sm text-gray-500">Bạn chưa mua gói dịch vụ nào.</p>
            <div class="mt-6">
                <a href="{{ route('packages') }}" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    Mua Gói Đầu Tiên
                </a>
            </div>
        </div>
    @endif

    @if($subscriptions->hasPages())
        <div class="mt-8">
            {{ $subscriptions->links() }}
        </div>
    @endif

    <!-- Details Modal -->
    <x-modal :show="$showDetailsModal" @close="$wire.closeDetailsModal()">
        @if($selectedSubscription)
            <div class="p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Chi Tiết Gói Dịch Vụ</h2>
                
                <!-- Package Info -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Thông Tin Gói</h3>
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Tên gói:</dt>
                                <dd class="font-medium">{{ $selectedSubscription->servicePackage->name }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Giá:</dt>
                                <dd class="font-medium">{{ number_format($selectedSubscription->servicePackage->price_monthly, 0, ',', '.') }} VNĐ/tháng</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Trạng thái:</dt>
                                <dd>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                        @switch($selectedSubscription->status)
                                            @case('ACTIVE') bg-green-100 text-green-800 @break
                                            @case('PENDING_PAYMENT') bg-yellow-100 text-yellow-800 @break
                                            @case('INACTIVE') bg-red-100 text-red-800 @break
                                            @case('CANCELED') bg-gray-100 text-gray-800 @break
                                            @default bg-blue-100 text-blue-800
                                        @endswitch
                                    ">{{ $selectedSubscription->status }}</span>
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600">Ngày bắt đầu:</dt>
                                <dd>{{ $selectedSubscription->created_at->format('d/m/Y H:i') }}</dd>
                            </div>
                            @if($selectedSubscription->ends_at)
                                <div class="flex justify-between">
                                    <dt class="text-gray-600">Ngày hết hạn:</dt>
                                    <dd class="{{ $selectedSubscription->ends_at->isPast() ? 'text-red-600' : 'text-gray-900' }}">
                                        {{ $selectedSubscription->ends_at->format('d/m/Y H:i') }}
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Tính Năng</h3>
                        <ul class="space-y-2 text-sm">
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                {{ $selectedSubscription->servicePackage->max_streams }} streams đồng thời
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Chất lượng tối đa: {{ $selectedSubscription->servicePackage->max_quality }}
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Lưu trữ: {{ $selectedSubscription->servicePackage->storage_limit ? \Illuminate\Support\Number::fileSize($selectedSubscription->servicePackage->storage_limit, precision: 1) : 'Không giới hạn' }}
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Transactions -->
                @if($selectedSubscription->transactions->count() > 0)
                    <div class="border-t border-gray-200 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">Lịch Sử Giao Dịch</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Mã GD</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Số Tiền</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Trạng Thái</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ngày</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($selectedSubscription->transactions as $transaction)
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ $transaction->payment_code }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-900">{{ number_format($transaction->amount, 0, ',', '.') }} VNĐ</td>
                                            <td class="px-4 py-2 text-sm">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                    @switch($transaction->status)
                                                        @case('COMPLETED') bg-green-100 text-green-800 @break
                                                        @case('PENDING') bg-yellow-100 text-yellow-800 @break
                                                        @case('FAILED') bg-red-100 text-red-800 @break
                                                        @case('CANCELLED') bg-gray-100 text-gray-800 @break
                                                        @default bg-blue-100 text-blue-800
                                                    @endswitch
                                                ">{{ $transaction->status }}</span>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-500">{{ $transaction->created_at->format('d/m/Y H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end mt-6">
                    <button wire:click="closeDetailsModal" 
                            class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-md">
                        Đóng
                    </button>
                </div>
            </div>
        @endif
    </x-modal>
</div> 