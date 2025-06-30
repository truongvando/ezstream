<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
    <!-- Header Section -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Thanh Toán Đơn Hàng</h1>
                        <p class="text-gray-600">Hoàn tất thanh toán để kích hoạt dịch vụ</p>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                    <span class="text-sm text-gray-600">Bảo mật SSL</span>
                </div>
            </div>
        </div>
    </div>

    @if($transaction)
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Status Banner -->
            <div wire:poll.10s class="mb-8">
                @if($subscription->status === 'ACTIVE')
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-xl p-6 text-white text-center">
                        <div class="flex items-center justify-center mb-4">
                            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-2xl font-bold mb-2">🎉 Thanh Toán Thành Công!</h2>
                        <p class="text-green-100 mb-4">Gói dịch vụ của bạn đã được kích hoạt thành công</p>
                        <div class="inline-flex items-center px-6 py-3 bg-white bg-opacity-20 rounded-lg">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Chuyển hướng trong 3 giây...</span>
                        </div>
                        <script>
                            setTimeout(() => {
                                window.location.href = "{{ route('dashboard') }}";
                            }, 3000);
                        </script>
                    </div>
                @else
                    <div class="bg-gradient-to-r from-yellow-400 to-orange-500 rounded-xl p-6 text-white text-center">
                        <div class="flex items-center justify-center mb-4">
                            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-2xl font-bold mb-2">⏳ Đang Chờ Thanh Toán</h2>
                        <p class="text-yellow-100">Vui lòng quét mã QR hoặc chuyển khoản theo thông tin bên dưới</p>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                <!-- QR Code Section -->
                <div class="xl:col-span-2">
                    <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                        <div class="text-center mb-8">
                            <h2 class="text-3xl font-bold text-gray-900 mb-4">Quét Mã QR Để Thanh Toán</h2>
                            <p class="text-lg text-gray-600">Sử dụng ứng dụng ngân hàng của bạn để quét mã VietQR</p>
                        </div>

                        <!-- QR Code -->
                        <div class="flex justify-center mb-8">
                            <div class="relative">
                                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 p-8 rounded-3xl shadow-inner">
                                    <img src="{{ $qrCodeUrl }}" alt="VietQR Code" class="w-80 h-80 mx-auto rounded-2xl shadow-lg">
                                </div>
                                <div class="absolute -top-4 -right-4 bg-blue-600 text-white px-4 py-2 rounded-full text-sm font-semibold shadow-lg">
                                    VietQR
                                </div>
                            </div>
                        </div>

                        <!-- Instructions -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="text-center">
                                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <span class="text-2xl font-bold text-blue-600">1</span>
                                </div>
                                <h3 class="font-semibold text-gray-900 mb-2">Mở App Ngân Hàng</h3>
                                <p class="text-sm text-gray-600">Mở ứng dụng ngân hàng hoặc ví điện tử của bạn</p>
                            </div>
                            <div class="text-center">
                                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <span class="text-2xl font-bold text-green-600">2</span>
                                </div>
                                <h3 class="font-semibold text-gray-900 mb-2">Quét Mã QR</h3>
                                <p class="text-sm text-gray-600">Sử dụng tính năng quét QR để thanh toán</p>
                            </div>
                            <div class="text-center">
                                <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <span class="text-2xl font-bold text-purple-600">3</span>
                                </div>
                                <h3 class="font-semibold text-gray-900 mb-2">Xác Nhận</h3>
                                <p class="text-sm text-gray-600">Kiểm tra thông tin và xác nhận thanh toán</p>
                            </div>
                        </div>

                        <!-- Manual Transfer Info -->
                        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Hoặc Chuyển Khoản Thủ Công
                            </h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ngân hàng:</label>
                                    <div class="bg-white px-4 py-3 rounded-lg border font-semibold text-gray-900">
                                        Vietcombank (VCB)
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Số tài khoản:</label>
                                    <div class="bg-white px-4 py-3 rounded-lg border font-mono text-gray-900 flex items-center justify-between">
                                        <span>0971000032314</span>
                                        <button onclick="copyToClipboard('0971000032314')" class="text-blue-600 hover:text-blue-800">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Chủ tài khoản:</label>
                                    <div class="bg-white px-4 py-3 rounded-lg border font-semibold text-gray-900">
                                        TRUONG VAN DO
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nội dung:</label>
                                    <div class="bg-white px-4 py-3 rounded-lg border font-mono text-gray-900 flex items-center justify-between">
                                        <span>{{ $transaction->payment_code }}</span>
                                        <button onclick="copyToClipboard('{{ $transaction->payment_code }}')" class="text-blue-600 hover:text-blue-800">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="xl:col-span-1">
                    <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100 sticky top-8">
                        <h3 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                            <svg class="w-6 h-6 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Chi Tiết Đơn Hàng
                        </h3>

                        <!-- Package Info -->
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-6 mb-6 border border-blue-100">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center mr-4">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-lg font-bold text-gray-900">{{ $subscription->servicePackage->name }}</h4>
                                    <p class="text-sm text-gray-600">{{ $subscription->servicePackage->description }}</p>
                                </div>
                            </div>
                            
                            <!-- Features -->
                            <div class="space-y-3">
                                <div class="flex items-center text-sm">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span class="text-gray-700">{{ $subscription->servicePackage->max_streams }} streams đồng thời</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span class="text-gray-700">Chất lượng tối đa: {{ $subscription->servicePackage->max_quality }}</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <span class="text-gray-700">Lưu trữ: {{ $subscription->servicePackage->storage_limit_gb ? $subscription->servicePackage->storage_limit_gb . ' GB' : 'Không giới hạn' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Transaction Details -->
                        <div class="space-y-4 mb-6">
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="text-gray-600">Mã giao dịch:</span>
                                <span class="font-mono bg-gray-100 px-3 py-1 rounded-lg text-sm">{{ $transaction->payment_code }}</span>
                            </div>
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="text-gray-600">Ngày tạo:</span>
                                <span class="font-medium">{{ $transaction->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <div class="flex justify-between items-center py-3 border-b border-gray-100">
                                <span class="text-gray-600">Phương thức:</span>
                                <span class="font-medium">Chuyển khoản ngân hàng</span>
                            </div>
                        </div>

                        <!-- Total Amount -->
                        <div class="bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl p-6 border border-green-200">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-semibold text-gray-900">Tổng thanh toán:</span>
                                <div class="text-right">
                                    <div class="text-3xl font-bold text-green-600">
                                        {{ number_format($transaction->amount, 0, ',', '.') }}
                                    </div>
                                    <div class="text-sm text-gray-600">VND</div>
                                </div>
                            </div>
                        </div>

                        <!-- Security Notice -->
                        <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <div>
                                    <h4 class="text-sm font-semibold text-blue-900 mb-1">Thanh toán an toàn</h4>
                                    <p class="text-xs text-blue-700">Giao dịch được bảo mật bằng công nghệ SSL và được xác minh tự động trong vòng 1-5 phút.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Support -->
                        <div class="mt-6 text-center">
                            <p class="text-sm text-gray-600 mb-2">Cần hỗ trợ?</p>
                            <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Liên hệ hỗ trợ
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <div class="bg-white rounded-2xl shadow-xl p-12">
                <div class="w-24 h-24 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"/>
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Không Tìm Thấy Thông Tin Thanh Toán</h2>
                <p class="text-gray-600 mb-8">Có vẻ như đơn hàng này không tồn tại hoặc đã được xử lý.</p>
                <a href="{{ route('packages') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg">
                    Quay lại chọn gói
                </a>
            </div>
        </div>
    @endif
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        const originalText = event.target.closest('button').innerHTML;
        event.target.closest('button').innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
        setTimeout(() => {
            event.target.closest('button').innerHTML = originalText;
        }, 2000);
    });
}
</script>
