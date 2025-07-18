<div class="bg-gray-50 dark:bg-gray-900" x-data="{
    copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            let originalIcon = event.target.closest('button').innerHTML;
            event.target.closest('button').innerHTML = `<svg class='w-4 h-4 text-green-500' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='currentColor'><path fill-rule='evenodd' d='M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.052-.143z' clip-rule='evenodd' /></svg>`;
            setTimeout(() => {
                event.target.closest('button').innerHTML = originalIcon;
            }, 2000);
        });
    }
}">
    @if($transaction)
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <!-- Polling Status Banner -->
            <div wire:poll.5s="checkPaymentStatus" class="mb-8">
                @if($subscription->status === 'ACTIVE')
                    <div class="bg-green-100 dark:bg-green-900/50 border-l-4 border-green-500 text-green-800 dark:text-green-200 p-6 rounded-lg shadow-md">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-bold">🎉 Thanh Toán Thành Công!</h3>
                                <div class="text-sm mt-1">
                                    Gói dịch vụ của bạn đã được kích hoạt. Đang chuyển hướng về trang quản lý...
                                </div>
                            </div>
                        </div>
                        <script>
                            setTimeout(() => { window.location.href = "{{ route('dashboard') }}"; }, 3000);
                        </script>
                    </div>
                @else
                    <div class="bg-blue-100 dark:bg-blue-900/50 border-l-4 border-blue-500 text-blue-800 dark:text-blue-200 p-6 rounded-lg shadow-md">
                        <div class="flex items-center">
                             <div class="flex-shrink-0">
                                <svg class="h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-bold">⏳ Đang chờ thanh toán</h3>
                                <div class="text-sm mt-1 flex items-center">
                                    <span class="relative flex h-2 w-2 mr-2">
                                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                      <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                                    </span>
                                    Hệ thống sẽ tự động xác nhận ngay khi nhận được chuyển khoản của bạn.
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8 items-start">
                <!-- Left Side: Payment Details -->
                <div class="lg:col-span-3 bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Thực hiện thanh toán</h2>
                    <p class="text-gray-600 dark:text-gray-300 mb-8">Sử dụng ứng dụng ngân hàng của bạn để quét mã hoặc chuyển khoản thủ công.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                        <!-- QR Code -->
                        <div class="text-center">
                            <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200 mb-4">Quét mã VietQR</h3>
                            <div class="max-w-xs mx-auto bg-white p-4 rounded-lg shadow-inner">
                                <img src="{{ $qrCodeUrl }}" alt="VietQR Code" class="w-full h-auto rounded-md">
                            </div>
                            <p class="text-xs text-gray-500 mt-3">Mã QR đã bao gồm số tiền và nội dung chuyển khoản.</p>
                        </div>
                        
                        <!-- Manual Transfer Info -->
                        <div>
                             <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200 mb-4">Thông tin chuyển khoản</h3>
                             <div class="space-y-4">
                                <div class="group">
                                    <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Ngân hàng</label>
                                    <p class="text-base font-semibold text-gray-900 dark:text-white">Vietcombank (VCB)</p>
                                </div>
                                <div class="group">
                                    <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Chủ tài khoản</label>
                                    <p class="text-base font-semibold text-gray-900 dark:text-white">TRUONG VAN DO</p>
                                </div>
                                <div class="group">
                                    <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Số tài khoản</label>
                                    <div class="flex items-center justify-between">
                                        <p class="text-base font-mono font-semibold text-blue-600 dark:text-blue-400">0971000032314</p>
                                        <button @click="copyToClipboard('0971000032314')" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 p-1 rounded-md -mr-1">
                                           <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v3.042m-7.332 0c-.055.194-.084.4-.084.612v3.042m0 9.75H12.5a2.25 2.25 0 002.25-2.25V5.25c0-1.03-.842-1.875-1.875-1.875h-1.5c-1.033 0-1.875.845-1.875 1.875v10.5A2.25 2.25 0 007.5 21h6" /></svg>
                                        </button>
                                    </div>
                                </div>
                                 <div class="group">
                                    <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Nội dung chuyển khoản</label>
                                     <div class="flex items-center justify-between">
                                        <p class="text-base font-mono font-semibold text-red-600 dark:text-red-400">{{ $this->paymentCode }}</p>
                                        <button @click="copyToClipboard('{{ $this->paymentCode }}')" class="text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 p-1 rounded-md -mr-1">
                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v3.042m-7.332 0c-.055.194-.084.4-.084.612v3.042m0 9.75H12.5a2.25 2.25 0 002.25-2.25V5.25c0-1.03-.842-1.875-1.875-1.875h-1.5c-1.033 0-1.875.845-1.875 1.875v10.5A2.25 2.25 0 007.5 21h6" /></svg>
                                        </button>
                                    </div>
                                </div>
                             </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Order Summary -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8 sticky top-8">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Tóm tắt đơn hàng</h3>
                    
                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-lg text-gray-900 dark:text-white">{{ $subscription->servicePackage->name }}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $subscription->servicePackage->description }}</p>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600 space-y-2 text-sm">
                            <div class="flex items-center text-gray-700 dark:text-gray-300">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>{{ $subscription->servicePackage->max_streams }} luồng đồng thời</span>
                            </div>
                             <div class="flex items-center text-gray-700 dark:text-gray-300">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Chất lượng tối đa: {{ $subscription->servicePackage->video_resolution }}p</span>
                            </div>
                             <div class="flex items-center text-gray-700 dark:text-gray-300">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Lưu trữ: {{ $subscription->servicePackage->storage_limit_gb ? $subscription->servicePackage->storage_limit_gb . ' GB' : 'Không giới hạn' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-300">Mã đơn hàng:</span>
                            <span class="font-mono text-gray-800 dark:text-gray-100 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded-md">{{ $this->paymentCode }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-300">Ngày tạo:</span>
                            <span class="font-medium text-gray-800 dark:text-gray-100">{{ $transaction->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                         <div class="flex justify-between items-center">
                            <span class="text-gray-600 dark:text-gray-300">Phương thức:</span>
                            <span class="font-medium text-gray-800 dark:text-gray-100">Chuyển khoản Ngân hàng</span>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex justify-between items-baseline">
                            <span class="text-lg font-semibold text-gray-800 dark:text-gray-100">Tổng cộng:</span>
                            <div class="text-right">
                                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ number_format($transaction->amount, 0, ',', '.') }} <span class="text-xl">VNĐ</span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Check Button -->
                    <div class="mt-6">
                        <button wire:click="checkPaymentStatus"
                                class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-4 rounded-lg shadow-md transition-all duration-200 transform hover:scale-105 flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Kiểm tra thanh toán
                        </button>
                    </div>

                    <div class="mt-6 text-center text-xs text-gray-500">
                        <p>Hệ thống tự động kiểm tra mỗi 5 giây. Bạn cũng có thể nhấn nút trên để kiểm tra ngay.</p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="flex items-center justify-center min-h-[calc(100vh-10rem)]">
            <div class="text-center max-w-lg mx-auto p-8">
                 <div class="w-24 h-24 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Không tìm thấy đơn hàng</h2>
                <p class="text-gray-600 dark:text-gray-300 mb-8">Đơn hàng này không tồn tại hoặc đã được xử lý. Vui lòng kiểm tra lại.</p>
                <a href="{{ route('packages') }}" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-lg shadow-md transition-transform transform hover:scale-105">
                    Quay lại trang chọn gói
                </a>
            </div>
        </div>
    @endif
</div>
