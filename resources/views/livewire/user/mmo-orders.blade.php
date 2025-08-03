<div class="max-w-7xl mx-auto p-6">
    <!-- Header -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">📦 Đơn hàng MMO</h1>
            <p class="text-gray-600 dark:text-gray-400">Theo dõi trạng thái và lịch sử đơn hàng dịch vụ MMO</p>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tổng đơn hàng</div>
            <div class="text-2xl font-bold text-blue-600">{{ number_format($stats['total_orders']) }}</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Đang chờ</div>
            <div class="text-2xl font-bold text-yellow-600">{{ number_format($stats['pending_orders']) }}</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Đang xử lý</div>
            <div class="text-2xl font-bold text-orange-600">{{ number_format($stats['processing_orders']) }}</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Hoàn thành</div>
            <div class="text-2xl font-bold text-green-600">{{ number_format($stats['completed_orders']) }}</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tổng chi tiêu</div>
            <div class="text-2xl font-bold text-purple-600">${{ number_format($stats['total_spent'], 2) }}</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="flex gap-4">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Lọc theo trạng thái</label>
                <select wire:model.live="statusFilter" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Tất cả trạng thái</option>
                    <option value="PENDING">🟡 Đang chờ</option>
                    <option value="PROCESSING">🔵 Đang xử lý</option>
                    <option value="COMPLETED">🟢 Hoàn thành</option>
                    <option value="CANCELLED">🔴 Đã hủy</option>
                </select>
            </div>
            <div class="flex items-end">
                <button wire:click="$set('statusFilter', '')"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm">
                    🔄 Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Orders List -->
    <div class="space-y-4 mb-6">
        @forelse($orders as $order)
            <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <h3 class="font-bold text-gray-900 dark:text-white">#{{ $order->order_code }}</h3>
                            <span class="px-3 py-1 text-sm rounded-full {{ $order->status_badge }}">
                                @if($order->status === 'PENDING') 🟡 Đang chờ
                                @elseif($order->status === 'PROCESSING') 🔵 Đang xử lý
                                @elseif($order->status === 'COMPLETED') 🟢 Hoàn thành
                                @elseif($order->status === 'CANCELLED') 🔴 Đã hủy
                                @elseif($order->status === 'REFUNDED') 🟠 Đã hoàn tiền
                                @endif
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Dịch vụ:</div>
                                <div class="font-medium text-gray-900 dark:text-white">{{ $order->mmoService->name }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Số tiền:</div>
                                <div class="font-bold text-green-600">{{ $order->formatted_amount }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Ngày đặt:</div>
                                <div class="text-gray-900 dark:text-white">{{ $order->created_at->format('d/m/Y H:i') }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Thời gian giao hàng:</div>
                                <div class="text-gray-900 dark:text-white">{{ $order->mmoService->delivery_time }}</div>
                            </div>
                        </div>

                        @if($order->customer_requirements && isset($order->customer_requirements['requirements']))
                            <div class="mb-4">
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Yêu cầu của bạn:</div>
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-sm text-gray-900 dark:text-white">
                                    {{ $order->customer_requirements['requirements'] }}
                                </div>
                            </div>
                        @endif

                        @if($order->delivery_notes)
                            <div class="mb-4">
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">📦 Ghi chú giao hàng:</div>
                                <div class="bg-green-50 dark:bg-green-900 rounded-lg p-3 text-sm text-green-800 dark:text-green-200">
                                    {{ $order->delivery_notes }}
                                </div>
                            </div>
                        @endif

                        @if($order->admin_notes)
                            <div class="mb-4">
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">📝 Ghi chú admin:</div>
                                <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-3 text-sm text-blue-800 dark:text-blue-200">
                                    {{ $order->admin_notes }}
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="ml-4 flex gap-2">
                        <button wire:click="openOrderModal({{ $order->id }})"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                            👁️ Chi tiết
                        </button>
                        @if($order->status === 'PENDING')
                            <button wire:click="openCancelModal({{ $order->id }})"
                                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm">
                                Hủy đơn
                            </button>
                        @elseif($order->status === 'PROCESSING' && !str_contains($order->admin_notes ?? '', 'YÊU CẦU HỦY'))
                            <button wire:click="openCancelModal({{ $order->id }})"
                                    class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-lg text-sm">
                                Yêu cầu hủy
                            </button>
                        @elseif($order->status === 'PROCESSING' && str_contains($order->admin_notes ?? '', 'YÊU CẦU HỦY'))
                            <span class="bg-yellow-100 text-yellow-800 px-4 py-2 rounded-lg text-sm">
                                Chờ admin duyệt hủy
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-12">
                <div class="text-6xl mb-4">📦</div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Chưa có đơn hàng nào</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Bạn chưa đặt đơn hàng dịch vụ MMO nào</p>
                <a href="{{ route('mmo-services.index') }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium">
                    🎮 Xem dịch vụ MMO
                </a>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($orders->hasPages())
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            {{ $orders->links() }}
        </div>
    @endif

    <!-- Order Detail Modal -->
    @if($showOrderModal && $selectedOrder)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">📋 Chi tiết đơn hàng</h3>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mã đơn hàng</label>
                            <div class="text-gray-900 dark:text-white font-mono">{{ $selectedOrder->order_code }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Trạng thái</label>
                            <span class="px-3 py-1 text-sm rounded-full {{ $selectedOrder->status_badge }}">
                                @if($selectedOrder->status === 'PENDING') 🟡 Đang chờ
                                @elseif($selectedOrder->status === 'PROCESSING') 🔵 Đang xử lý
                                @elseif($selectedOrder->status === 'COMPLETED') 🟢 Hoàn thành
                                @elseif($selectedOrder->status === 'CANCELLED') 🔴 Đã hủy
                                @elseif($selectedOrder->status === 'REFUNDED') 🟠 Đã hoàn tiền
                                @endif
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dịch vụ</label>
                            <div class="text-gray-900 dark:text-white">{{ $selectedOrder->mmoService->name }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Số tiền</label>
                            <div class="text-lg font-bold text-green-600">{{ $selectedOrder->formatted_amount }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ngày đặt</label>
                            <div class="text-gray-900 dark:text-white">{{ $selectedOrder->created_at->format('d/m/Y H:i:s') }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Thời gian giao hàng</label>
                            <div class="text-gray-900 dark:text-white">{{ $selectedOrder->mmoService->delivery_time }}</div>
                        </div>
                    </div>

                    @if($selectedOrder->customer_requirements && isset($selectedOrder->customer_requirements['requirements']))
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Yêu cầu của bạn</label>
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 text-sm text-gray-900 dark:text-white">
                                {{ $selectedOrder->customer_requirements['requirements'] }}
                            </div>
                        </div>
                    @endif

                    @if($selectedOrder->delivery_notes)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">📦 Ghi chú giao hàng</label>
                            <div class="bg-green-50 dark:bg-green-900 rounded-lg p-3 text-sm text-green-800 dark:text-green-200">
                                {{ $selectedOrder->delivery_notes }}
                            </div>
                        </div>
                    @endif

                    @if($selectedOrder->admin_notes)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">📝 Ghi chú admin</label>
                            <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-3 text-sm text-blue-800 dark:text-blue-200">
                                {{ $selectedOrder->admin_notes }}
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex gap-3 mt-6">
                    <button wire:click="closeOrderModal"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        ❌ Đóng
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Cancel Order Modal -->
    @if($showCancelModal)
        @php $cancelOrder = \App\Models\MmoOrder::find($selectedOrderId); @endphp
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">❌ Hủy đơn hàng</h3>

                @if($cancelOrder)
                    <div class="mb-4 p-3 bg-gray-100 dark:bg-gray-700 rounded">
                        <div class="font-medium">Đơn hàng #{{ $cancelOrder->order_code }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Dịch vụ: {{ $cancelOrder->mmoService->name }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Số tiền: ${{ number_format($cancelOrder->amount, 2) }}</div>
                    </div>
                @endif

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Lý do hủy đơn *</label>
                    <textarea wire:model="cancelReason" rows="3" maxlength="500"
                              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                              placeholder="Vui lòng cho biết lý do hủy đơn hàng..."></textarea>
                    @error('cancelReason') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg p-3 mb-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Lưu ý</h3>
                            <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                @if($cancelOrder && $cancelOrder->status === 'PENDING')
                                    <p>• Đơn PENDING sẽ được hủy ngay lập tức</p>
                                    <p>• Số tiền sẽ được hoàn lại vào tài khoản của bạn</p>
                                    <p>• Đơn hàng không thể khôi phục sau khi hủy</p>
                                @else
                                    <p>• Đơn PROCESSING cần admin duyệt mới được hủy</p>
                                    <p>• Yêu cầu hủy sẽ được gửi đến admin</p>
                                    <p>• Admin sẽ xem xét và phản hồi trong thời gian sớm nhất</p>
                                    <p>• Nếu được duyệt, tiền sẽ được hoàn lại</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button wire:click="cancelOrder"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            class="flex-1 bg-red-600 hover:bg-red-700 disabled:hover:bg-red-600 text-white font-bold py-2 px-4 rounded">
                        <span wire:loading.remove wire:target="cancelOrder">✅ Xác nhận hủy</span>
                        <span wire:loading wire:target="cancelOrder" class="flex items-center justify-center">
                            <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Đang hủy...
                        </span>
                    </button>
                    <button wire:click="closeCancelModal"
                            wire:loading.attr="disabled"
                            wire:target="cancelOrder"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 disabled:opacity-50 text-white font-bold py-2 px-4 rounded">
                        🔙 Quay lại
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
