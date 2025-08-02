<x-sidebar-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            💳 Thanh toán Tool
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Order Information -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">📋 Thông tin đơn hàng</h3>
                
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Mã đơn hàng:</span>
                        <span class="font-medium text-gray-900 dark:text-white">#{{ $toolOrder->id }}</span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Tool:</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $toolOrder->tool->name }}</span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600 dark:text-gray-400">Mô tả:</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ Str::limit($toolOrder->tool->short_description, 40) }}</span>
                    </div>
                    
                    @if($toolOrder->tool->is_on_sale)
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Giá gốc:</span>
                            <span class="line-through text-gray-400">{{ number_format($toolOrder->tool->price) }}đ</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Giá sale:</span>
                            <span class="font-medium text-red-600 dark:text-red-400">{{ number_format($toolOrder->tool->sale_price) }}đ</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Tiết kiệm:</span>
                            <span class="font-medium text-green-600 dark:text-green-400">
                                {{ number_format($toolOrder->tool->price - $toolOrder->tool->sale_price) }}đ 
                                (-{{ round((($toolOrder->tool->price - $toolOrder->tool->sale_price) / $toolOrder->tool->price) * 100) }}%)
                            </span>
                        </div>
                    @endif
                    
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex justify-between text-lg font-bold">
                            <span class="text-gray-900 dark:text-white">Tổng cộng:</span>
                            <span class="text-purple-600 dark:text-purple-400">{{ number_format($toolOrder->amount) }} VND</span>
                        </div>
                    </div>
                </div>

                <!-- Tool Preview -->
                <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="font-medium text-gray-900 dark:text-white mb-3">🖼️ Preview Tool</h4>
                    <img src="{{ $toolOrder->tool->image }}" alt="{{ $toolOrder->tool->name }}" 
                         class="w-full h-32 object-cover rounded-lg">
                </div>
            </div>

            <!-- Payment Information -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">💰 Thông tin thanh toán</h3>
                
                <div class="space-y-4">
                    <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4">
                        <h4 class="font-medium text-purple-900 dark:text-purple-100 mb-2">Mã thanh toán</h4>
                        <div class="flex items-center space-x-2">
                            <code class="bg-white dark:bg-gray-700 text-purple-600 dark:text-purple-400 px-3 py-2 rounded font-mono text-lg font-bold flex-1">
                                {{ $transaction->payment_code }}
                            </code>
                            <button onclick="copyToClipboard('{{ $transaction->payment_code }}')" 
                                    class="bg-purple-600 hover:bg-purple-700 text-white p-2 rounded transition-colors"
                                    title="Copy mã thanh toán">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- QR Code -->
                    <div class="text-center">
                        <div class="bg-white p-4 rounded-lg inline-block">
                            @php
                                $bankId = '970436'; // Vietcombank
                                $accountNo = '0971000032314';
                                $accountName = 'TRUONG VAN DO';
                                $qrUrl = "https://img.vietqr.io/image/{$bankId}-{$accountNo}-compact2.png?" . http_build_query([
                                    'amount' => $transaction->amount,
                                    'addInfo' => $transaction->payment_code,
                                    'accountName' => $accountName,
                                ]);
                            @endphp
                            <img src="{{ $qrUrl }}" alt="QR Code thanh toán" class="w-64 h-64 mx-auto">
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                            Quét mã QR để thanh toán nhanh
                        </p>
                    </div>

                    <!-- Bank Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-3">🏦 Thông tin chuyển khoản</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Ngân hàng:</span>
                                <span class="font-medium text-gray-900 dark:text-white">Vietcombank</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Số tài khoản:</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $accountNo }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Chủ tài khoản:</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $accountName }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Số tiền:</span>
                                <span class="font-medium text-red-600 dark:text-red-400">{{ number_format($transaction->amount) }} VND</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Nội dung:</span>
                                <span class="font-medium text-purple-600 dark:text-purple-400">{{ $transaction->payment_code }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Important Notes -->
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                        <h4 class="font-medium text-yellow-900 dark:text-yellow-100 mb-2">⚠️ Lưu ý quan trọng</h4>
                        <ul class="text-sm text-yellow-800 dark:text-yellow-200 space-y-1">
                            <li>• Chuyển khoản đúng số tiền: <strong>{{ number_format($transaction->amount) }} VND</strong></li>
                            <li>• Ghi đúng nội dung: <strong>{{ $transaction->payment_code }}</strong></li>
                            <li>• Sau khi thanh toán thành công, bạn sẽ nhận được license key</li>
                            <li>• Tool sẽ có sẵn để download trong mục "Quản lý License"</li>
                            <li>• Thời gian xử lý: 1-5 phút</li>
                        </ul>
                    </div>

                    <!-- What you'll get -->
                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                        <h4 class="font-medium text-green-900 dark:text-green-100 mb-2">🎁 Bạn sẽ nhận được</h4>
                        <ul class="text-sm text-green-800 dark:text-green-200 space-y-1">
                            <li>• ✅ License key để kích hoạt tool</li>
                            <li>• ✅ Link download tool</li>
                            <li>• ✅ Hướng dẫn sử dụng</li>
                            <li>• ✅ Hỗ trợ kỹ thuật</li>
                            @if($toolOrder->tool->features)
                                @foreach(array_slice($toolOrder->tool->features, 0, 3) as $feature)
                                    <li>• ✅ {{ $feature }}</li>
                                @endforeach
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 flex justify-center space-x-4">
            <a href="{{ route('tools.show', $toolOrder->tool->slug) }}" 
               class="bg-gray-600 hover:bg-gray-700 text-white font-medium px-6 py-3 rounded-lg transition-colors">
                ← Quay lại
            </a>
            <button onclick="checkPaymentStatus()" 
                    class="bg-purple-600 hover:bg-purple-700 text-white font-medium px-6 py-3 rounded-lg transition-colors">
                🔄 Kiểm tra thanh toán
            </button>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Đã copy mã thanh toán: ' + text);
            });
        }

        function checkPaymentStatus() {
            // Reload page to check payment status
            window.location.reload();
        }

        // Auto refresh every 30 seconds
        setInterval(() => {
            window.location.reload();
        }, 30000);
    </script>
</x-sidebar-layout>
