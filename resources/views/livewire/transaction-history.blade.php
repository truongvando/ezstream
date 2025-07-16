<div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden">
            <div class="p-6">
                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Lịch Sử Giao Dịch</h1>
                <p class="mt-2 text-gray-600 dark:text-gray-300">Theo dõi tất cả các giao dịch thanh toán và nâng cấp của bạn.</p>
            </div>
            
            @if($transactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ngày Giao Dịch</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mã Giao Dịch</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mô Tả</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Số Tiền</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trạng Thái</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Hành Động</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($transactions as $transaction)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                        {{ $transaction->created_at->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-800 dark:text-gray-100">
                                        {{ $transaction->payment_code }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                        {{ $transaction->description }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ number_format($transaction->amount, 0, ',', '.') }} VNĐ
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @switch($transaction->status)
                                                @case('COMPLETED') bg-green-100 text-green-800 dark:bg-green-800/50 dark:text-green-200 @break
                                                @case('PENDING') bg-yellow-100 text-yellow-800 dark:bg-yellow-800/50 dark:text-yellow-200 @break
                                                @case('FAILED') bg-red-100 text-red-800 dark:bg-red-800/50 dark:text-red-200 @break
                                                @default bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-100
                                            @endswitch
                                        ">
                                            @switch($transaction->status)
                                                @case('COMPLETED') Hoàn thành @break
                                                @case('PENDING') Đang chờ @break
                                                @case('FAILED') Thất bại @break
                                                @default {{ ucfirst(strtolower($transaction->status)) }}
                                            @endswitch
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        @if($transaction->status === 'PENDING')
                                            <button wire:click="cancelTransaction({{ $transaction->id }})" 
                                                    wire:confirm="Bạn có chắc chắn muốn hủy giao dịch này không? Hành động này không thể hoàn tác."
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 font-semibold">
                                                Hủy
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                @if($transactions->hasPages())
                    <div class="p-6 border-t border-gray-200 dark:border-gray-700">
                        {{ $transactions->links() }}
                    </div>
                @endif
            @else
                <div class="text-center py-16">
                     <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Không có giao dịch nào</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Lịch sử các lần thanh toán của bạn sẽ được hiển thị tại đây.</p>
                </div>
            @endif
        </div>
    </div>
</div> 