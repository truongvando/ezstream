<div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Quản lý đơn hàng treo</h2>
            <p class="text-gray-600 dark:text-gray-400">Xử lý các đơn hàng bị treo do hết tiền hoặc lỗi API</p>
        </div>
        <div class="flex gap-2">
            <button wire:click="retryAllPendingFunds" 
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                🔄 Retry tất cả đơn hết tiền
            </button>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        @php
            $statusConfig = [
                'PENDING_FUNDS' => ['label' => 'Hết tiền', 'color' => 'yellow', 'icon' => '💰'],
                'PENDING_RETRY' => ['label' => 'Chờ retry', 'color' => 'blue', 'icon' => '🔄'],
                'FAILED' => ['label' => 'Thất bại', 'color' => 'red', 'icon' => '❌'],
                'PROCESSING' => ['label' => 'Đang xử lý', 'color' => 'green', 'icon' => '⚡'],
                'COMPLETED' => ['label' => 'Hoàn thành', 'color' => 'emerald', 'icon' => '✅']
            ];
        @endphp

        @foreach($statusConfig as $status => $config)
            <div wire:click="$set('selectedStatus', '{{ $status }}')" 
                 class="cursor-pointer p-4 rounded-lg border-2 transition-all hover:scale-105
                        @if($selectedStatus === $status) 
                            border-{{ $config['color'] }}-500 bg-{{ $config['color'] }}-50 dark:bg-{{ $config['color'] }}-900/20 
                        @else 
                            border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-{{ $config['color'] }}-300 
                        @endif">
                <div class="text-center">
                    <div class="text-2xl mb-1">{{ $config['icon'] }}</div>
                    <div class="font-bold text-lg text-{{ $config['color'] }}-600 dark:text-{{ $config['color'] }}-400">
                        {{ $statusCounts[$status] ?? 0 }}
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ $config['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Search -->
    <div class="mb-6">
        <input wire:model.live="searchTerm" type="text" 
               class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" 
               placeholder="Tìm kiếm theo ID đơn hàng, link, user...">
    </div>

    <!-- Orders Table -->
    <div class="overflow-x-auto">
        <table class="w-full table-auto">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-700">
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Service</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Link</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Số lượng</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Giá</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trạng thái</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Thời gian</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Hành động</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($orders as $order)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                            #{{ $order->id }}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            <div>{{ $order->user->name }}</div>
                            <div class="text-xs text-gray-500">{{ $order->user->email }}</div>
                        </td>
                        <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">
                            <div class="max-w-xs truncate">{{ $order->service_id }}</div>
                        </td>
                        <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">
                            <div class="max-w-xs truncate">
                                <a href="{{ $order->link }}" target="_blank" class="text-blue-600 hover:underline">
                                    {{ $order->link }}
                                </a>
                            </div>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            {{ number_format($order->quantity) }}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                            ${{ number_format($order->total_amount, 2) }}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            @php
                                $statusColors = [
                                    'PENDING_FUNDS' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                    'PENDING_RETRY' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                    'FAILED' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                    'PROCESSING' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                    'COMPLETED' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                                    'PENDING' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                ];
                            @endphp
                            <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusColors[$order->status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ $order->status }}
                            </span>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            {{ $order->created_at->diffForHumans() }}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                            <div class="flex gap-2">
                                @if(in_array($order->status, ['PENDING_FUNDS', 'PENDING_RETRY', 'FAILED']))
                                    <button wire:click="retryOrder({{ $order->id }})" 
                                            @if(in_array($order->id, $processingOrders)) disabled @endif
                                            class="bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white px-3 py-1 rounded text-xs">
                                        @if(in_array($order->id, $processingOrders))
                                            ⏳
                                        @else
                                            🔄 Retry
                                        @endif
                                    </button>
                                @endif
                                
                                @if(in_array($order->status, ['PENDING_FUNDS', 'PENDING_RETRY', 'FAILED', 'PENDING']))
                                    <button wire:click="cancelOrder({{ $order->id }})" 
                                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs">
                                        ❌ Hủy
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            Không có đơn hàng nào
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $orders->links() }}
    </div>
</div>
