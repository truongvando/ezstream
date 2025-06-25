<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Quản lý Gói & Thanh toán') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-12">

            @if(Auth::user()->isAdmin())
                <!-- Admin Notice -->
                <div class="p-4 sm:p-8 bg-blue-50 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-700 shadow sm:rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <h3 class="text-lg font-medium text-blue-900 dark:text-blue-100">Tài khoản Admin</h3>
                            <p class="text-blue-700 dark:text-blue-300">Bạn là admin và có thể sử dụng tất cả tính năng mà không cần mua gói dịch vụ.</p>
                        </div>
                    </div>
                </div>
            @else
                <!-- Current Subscription for regular users -->
                <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            Gói Dịch Vụ Hiện Tại
                        </h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Thông tin về gói dịch vụ bạn đang sử dụng.
                        </p>

                        @if ($currentSubscription)
                            <div class="mt-6 p-6 bg-green-50 dark:bg-green-900/50 border border-green-200 dark:border-green-700 rounded-lg">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="text-xl font-bold text-green-800 dark:text-green-300">{{ $currentSubscription->servicePackage->name }}</h3>
                                        <p class="text-gray-700 dark:text-gray-300 mt-1">Trạng thái: <span class="font-semibold text-green-600 dark:text-green-400">Đang hoạt động</span></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($currentSubscription->servicePackage->price_monthly, 0, ',', '.') }} VNĐ/tháng</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Ngày hết hạn: {{ $currentSubscription->expires_at ? $currentSubscription->expires_at->format('d/m/Y') : 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="mt-6 p-6 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg text-center">
                                <p class="text-gray-600 dark:text-gray-400">Bạn chưa đăng ký gói dịch vụ nào.</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Available Packages -->
            @if(!Auth::user()->isAdmin())
                <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Các Gói Dịch Vụ Có Sẵn
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 mb-6">
                        Chọn gói phù hợp với nhu cầu của bạn.
                    </p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @foreach ($packages as $package)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-6 bg-white dark:bg-gray-800">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ $package->name }}</h3>
                                <p class="mt-2 text-3xl font-black text-gray-900 dark:text-white">
                                    {{ number_format($package->price_monthly, 0, ',', '.') }} <span class="text-base font-medium text-gray-500 dark:text-gray-400">VND/tháng</span>
                                </p>
                                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">{{ $package->description }}</p>
                                
                                <div class="mt-6">
                                    <button class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition">
                                        Chọn Gói Này
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Transaction History -->
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Lịch Sử Giao Dịch
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400 mb-6">
                    Danh sách các giao dịch gần đây của bạn.
                </p>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mã giao dịch</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Số tiền</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trạng thái</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ngày tạo</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($transactions as $transaction)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $transaction->payment_code }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                        {{ number_format($transaction->amount, 0, ',', '.') }} VND
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span @class([
                                            'px-2 inline-flex text-xs leading-5 font-semibold rounded-full',
                                            'bg-green-100 text-green-800' => $transaction->status === 'COMPLETED',
                                            'bg-yellow-100 text-yellow-800' => $transaction->status === 'PENDING',
                                            'bg-red-100 text-red-800' => $transaction->status === 'FAILED',
                                        ])>
                                            {{ $transaction->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                        {{ $transaction->created_at->format('d/m/Y H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                        Chưa có giao dịch nào.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($transactions->hasPages())
                    <div class="mt-4">
                        {{ $transactions->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
