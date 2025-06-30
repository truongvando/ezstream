<div wire:poll.2s="checkPaymentStatus">
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Page Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Gói Dịch Vụ</h1>
                <p class="mt-2 text-gray-600 dark:text-gray-400">Quản lý gói dịch vụ, thanh toán và lịch sử giao dịch</p>
            </div>

            <!-- Flash Messages -->
            @if (session()->has('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-r-lg" role="alert">
                    <p class="font-medium">{{ session('success') }}</p>
                </div>
            @endif
            
            @if (session()->has('error'))
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-r-lg" role="alert">
                    <p class="font-medium">{{ session('error') }}</p>
                </div>
            @endif

            @if(session()->has('info'))
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded-r-lg" role="alert">
                    <p class="font-medium">{{ session('info') }}</p>
                </div>
            @endif

            <!-- Admin Notice -->
            @if(Auth::user()->isAdmin())
                <div class="bg-blue-50 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-700 rounded-lg p-6 mb-8">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <h3 class="text-lg font-medium text-blue-900 dark:text-blue-100">Tài khoản Admin</h3>
                            <p class="text-blue-700 dark:text-blue-300">Bạn có quyền admin và có thể sử dụng tất cả tính năng mà không cần mua gói dịch vụ.</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 dark:border-gray-700 mb-8">
                <nav class="-mb-px flex space-x-8">
                    <button wire:click="switchTab('packages')" 
                            class="py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 {{ $activeTab === 'packages' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        Gói Dịch Vụ
                    </button>

                    @if($paymentTransaction)
                    <button wire:click="switchTab('payment')" 
                            class="py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 {{ $activeTab === 'payment' ? 'border-yellow-500 text-yellow-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                        Thanh Toán
                        <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            Chờ thanh toán
                        </span>
                    </button>
                    @endif

                    <button wire:click="switchTab('history')" 
                            class="py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200 {{ $activeTab === 'history' ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Lịch Sử
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="space-y-8">
                
                <!-- Packages Tab -->
                @if($activeTab === 'packages')
                    <!-- Current Subscription -->
                    @if(!Auth::user()->isAdmin())
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Gói Hiện Tại</h2>
                        
                        @if($activeSubscription)
                            <div class="bg-green-50 dark:bg-green-900/50 border border-green-200 dark:border-green-700 rounded-lg p-4">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-bold text-green-800 dark:text-green-300">
                                            {{ $activeSubscription->servicePackage->name }}
                                            <span class="text-sm font-normal text-green-600 dark:text-green-400">
                                                ({{ $activeSubscription->servicePackage->max_streams }} streams)
                                            </span>
                                        </h3>
                                        <p class="text-green-700 dark:text-green-300 mt-1">
                                            Trạng thái: <span class="font-semibold">Đang hoạt động</span>
                                        </p>
                                        <p class="text-sm text-green-600 dark:text-green-400 mt-1">
                                            Hết hạn: {{ $activeSubscription->ends_at->format('d/m/Y') }}
                                            @php
                                                $daysRemaining = 0;
                                                if ($activeSubscription->ends_at->isFuture()) {
                                                    $daysRemaining = now()->startOfDay()->diffInDays($activeSubscription->ends_at->startOfDay());
                                                }
                                            @endphp
                                            @if($daysRemaining > 0)
                                                <span class="font-medium">(còn {{ $daysRemaining }} ngày)</span>
                                            @else
                                                <span class="font-medium">(đã hết hạn)</span>
                                            @endif
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xl font-bold text-green-800 dark:text-green-300">
                                            {{ number_format($activeSubscription->servicePackage->price, 0, ',', '.') }}
                                        </p>
                                        <p class="text-sm text-green-600 dark:text-green-400">VNĐ/tháng</p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg p-6 text-center">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <p class="text-gray-600 dark:text-gray-400 text-lg">Bạn chưa đăng ký gói dịch vụ nào</p>
                                <p class="text-gray-500 dark:text-gray-500 text-sm mt-2">Chọn một gói bên dưới để bắt đầu</p>
                            </div>
                        @endif
                    </div>

                    <!-- Available Packages -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-6">
                            {{ $activeSubscription ? 'Nâng Cấp Gói' : 'Chọn Gói Dịch Vụ' }}
                        </h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            @foreach($packages as $package)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:shadow-lg transition-shadow duration-200 {{ $activeSubscription && $package->id === $activeSubscription->service_package_id ? 'ring-2 ring-green-500 bg-green-50 dark:bg-green-900/20' : '' }}">
                                    <div class="text-center">
                                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">{{ $package->name }}</h3>
                                        <div class="mb-4">
                                            <span class="text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($package->price, 0, ',', '.') }}</span>
                                            <span class="text-gray-500 dark:text-gray-400"> VNĐ/tháng</span>
                                        </div>
                                        <p class="text-gray-600 dark:text-gray-400 text-sm mb-6">{{ $package->description }}</p>
                                        
                                        <!-- Features -->
                                        @if($package->features)
                                            <ul class="text-left text-sm text-gray-600 dark:text-gray-400 mb-6 space-y-2">
                                                @foreach($package->features as $feature)
                                                    <li class="flex items-center">
                                                        <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                        {{ $feature }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        
                                        <!-- Action Button -->
                                        @if($activeSubscription && $package->id === $activeSubscription->service_package_id)
                                            <button disabled class="w-full bg-green-100 text-green-800 py-3 px-4 rounded-lg font-medium cursor-not-allowed">
                                                Đang sử dụng
                                            </button>
                                        @elseif($activeSubscription && $package->price <= $activeSubscription->servicePackage->price)
                                            <button disabled title="Bạn chỉ có thể nâng cấp lên gói cao hơn" class="w-full bg-gray-100 text-gray-500 py-3 px-4 rounded-lg font-medium cursor-not-allowed">
                                                Không thể hạ cấp
                                            </button>
                                        @else
                                            <button wire:click="selectPackage({{ $package->id }})" 
                                                    wire:loading.attr="disabled"
                                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 px-4 rounded-lg font-medium transition-colors duration-200 disabled:opacity-50">
                                                <span wire:loading.remove wire:target="selectPackage({{ $package->id }})">
                                                    {{ $activeSubscription ? 'Nâng cấp' : 'Chọn gói này' }}
                                                </span>
                                                <span wire:loading wire:target="selectPackage({{ $package->id }})">
                                                    Đang xử lý...
                                                </span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                @endif

                <!-- Payment Tab -->
                @if($activeTab === 'payment' && $paymentTransaction)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                        <div class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-2xl font-bold">Thanh Toán Đơn Hàng</h2>
                                    <p class="text-yellow-100 mt-1">Quét mã QR hoặc chuyển khoản để hoàn tất thanh toán</p>
                                </div>
                                <button wire:click="cancelPayment" 
                                        wire:confirm="Bạn có chắc muốn hủy giao dịch này?"
                                        class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition-colors duration-200">
                                    Hủy giao dịch
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 p-6">
                            <!-- QR Code Section -->
                            <div class="text-center">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quét mã QR để thanh toán</h3>
                                @if($qrCodeUrl)
                                    <div class="inline-block p-4 bg-white rounded-lg shadow-lg">
                                        <img src="{{ $qrCodeUrl }}" alt="QR Code thanh toán" class="w-64 h-64 mx-auto">
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-4">
                                        Sử dụng app ngân hàng để quét mã QR
                                    </p>
                                @endif
                            </div>

                            <!-- Payment Details -->
                            <div class="space-y-6">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Thông tin chuyển khoản</h3>
                                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 space-y-3">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Ngân hàng:</span>
                                            <span class="font-medium text-gray-900 dark:text-white">Vietcombank (VCB)</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Số tài khoản:</span>
                                            <span class="font-mono font-medium text-gray-900 dark:text-white">0971000032314</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Chủ tài khoản:</span>
                                            <span class="font-medium text-gray-900 dark:text-white">TRUONG VAN DO</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Số tiền:</span>
                                            <span class="font-bold text-lg text-red-600">{{ number_format($paymentTransaction->amount, 0, ',', '.') }} VNĐ</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Nội dung:</span>
                                            <span class="font-mono font-medium text-gray-900 dark:text-white">{{ $paymentTransaction->payment_code }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Package Info -->
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Chi tiết gói dịch vụ</h3>
                                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                                        <h4 class="font-bold text-blue-900 dark:text-blue-300">{{ $paymentTransaction->subscription->servicePackage->name }}</h4>
                                        <p class="text-blue-700 dark:text-blue-400 text-sm mt-1">{{ $paymentTransaction->subscription->servicePackage->description }}</p>
                                        <p class="text-blue-800 dark:text-blue-300 font-medium mt-2">{{ $paymentTransaction->description }}</p>
                                    </div>
                                </div>

                                <!-- Warning -->
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-yellow-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"/>
                                        </svg>
                                        <div>
                                            <h4 class="text-sm font-semibold text-yellow-800 dark:text-yellow-300">Lưu ý quan trọng</h4>
                                            <p class="text-xs text-yellow-700 dark:text-yellow-400 mt-1">
                                                Vui lòng chuyển đúng số tiền và ghi đúng nội dung để hệ thống tự động xác nhận thanh toán.
                                                Giao dịch sẽ được xử lý trong vòng 1-5 phút.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Manual Check Button -->
                                <div class="text-center">
                                    <button wire:click="manualCheckPayment" 
                                            wire:loading.attr="disabled"
                                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors duration-200 disabled:opacity-50">
                                        <span wire:loading.remove wire:target="manualCheckPayment">
                                            🔍 Kiểm tra ngay
                                        </span>
                                        <span wire:loading wire:target="manualCheckPayment">
                                            Đang kiểm tra...
                                        </span>
                                    </button>
                                    <p class="text-xs text-gray-500 mt-2">
                                        Nhấn nút này nếu bạn đã thanh toán và muốn kiểm tra ngay
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- History Tab -->
                @if($activeTab === 'history')
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Lịch Sử Giao Dịch</h2>
                        </div>
                        
                        @if($transactions->count() > 0)
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mã giao dịch</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Gói dịch vụ</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Số tiền</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trạng thái</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ngày tạo</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($transactions as $transaction)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-white">
                                                    {{ $transaction->payment_code }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                    {{ $transaction->subscription->servicePackage->name ?? 'N/A' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ number_format($transaction->amount, 0, ',', '.') }} VNĐ
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                        @switch($transaction->status)
                                                            @case('COMPLETED') bg-green-100 text-green-800 @break
                                                            @case('PENDING') bg-yellow-100 text-yellow-800 @break
                                                            @case('FAILED') bg-red-100 text-red-800 @break
                                                            @case('CANCELED') bg-gray-100 text-gray-800 @break
                                                            @case('UPGRADED') bg-gray-100 text-gray-800 @break
                                                            @case('INACTIVE') bg-gray-100 text-gray-800 @break
                                                            @default bg-gray-100 text-gray-800
                                                        @endswitch
                                                    ">
                                                        @switch($transaction->status)
                                                            @case('COMPLETED') Hoàn thành @break
                                                            @case('PENDING') Chờ thanh toán @break
                                                            @case('FAILED') Thất bại @break
                                                            @case('CANCELED') Đã hủy @break
                                                            @case('UPGRADED') Đã hủy (nâng cấp) @break
                                                            @case('INACTIVE') Đã hủy @break
                                                            @default {{ $transaction->status }}
                                                        @endswitch
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                                    {{ $transaction->created_at->format('d/m/Y H:i') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            @if($transactions->hasPages())
                                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                    {{ $transactions->links() }}
                                </div>
                            @endif
                        @else
                            <div class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Chưa có giao dịch nào</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Lịch sử giao dịch sẽ hiển thị ở đây sau khi bạn mua gói dịch vụ.</p>
                            </div>
                        @endif
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Xử lý khi thanh toán thành công
    Livewire.on('paymentSuccess', (data) => {
        console.log('Payment success event received!', data);
        alert('🎉 Thanh toán thành công! Giao diện sẽ được tải lại.');
        window.location.reload();
    });
</script>
@endpush
