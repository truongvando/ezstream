<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Admin Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Stat Widgets -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Total Users -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                    <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tổng Users</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_users'] }}</p>
                    </div>
                </div>
                <!-- Active Streams -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                    <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Streams Đang Chạy</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['active_streams'] }}</p>
                    </div>
                </div>
                <!-- Active VPS -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                    <div class="bg-purple-100 dark:bg-purple-900 p-3 rounded-full">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">VPS Hoạt Động</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['active_vps_servers'] }}</p>
                    </div>
                </div>
                <!-- Total Revenue -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md flex items-center space-x-4">
                    <div class="bg-yellow-100 dark:bg-yellow-900 p-3 rounded-full">
                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v.01"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Doanh Thu</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_revenue'], 0, ',', '.') }} VNĐ</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Recent Streams -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Streams Gần Đây</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">Stream</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">VPS</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">Status</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($recentStreams as $stream)
                                    <tr>
                                        <td class="py-3">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $stream->title }}</div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">by {{ $stream->user->name }}</div>
                                            </div>
                                        </td>
                                        <td class="py-3 text-sm text-gray-900 dark:text-gray-300">{{ $stream->vpsServer->name ?? 'N/A' }}</td>
                                        <td class="py-3">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                @switch($stream->status)
                                                    @case('ACTIVE')
                                                    @case('STREAMING') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 @break
                                                    @case('INACTIVE')
                                                    @case('STOPPED') bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200 @break
                                                    @case('ERROR') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 @break
                                                    @case('STARTING')
                                                    @case('STOPPING') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @break
                                                    @default bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                                @endswitch
                                            ">{{ $stream->status }}</span>
                                        </td>
                                        <td class="py-3">
                                            <button class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Chi tiết</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-4 text-center text-gray-500 dark:text-gray-400">Không có stream nào gần đây.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Recent Transactions -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Giao Dịch Gần Đây</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">User</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">Amount</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">Package</th>
                                        <th class="text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($recentTransactions as $transaction)
                                    <tr>
                                        <td class="py-3 text-sm text-gray-900 dark:text-gray-300">{{ $transaction->user->name }}</td>
                                        <td class="py-3 text-sm font-medium text-gray-900 dark:text-white">{{ number_format($transaction->amount, 0, ',', '.') }} VNĐ</td>
                                        <td class="py-3 text-sm text-gray-900 dark:text-gray-300">{{ $transaction->servicePackage->name ?? 'N/A' }}</td>
                                        <td class="py-3">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                {{ $transaction->status === 'COMPLETED' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' }}">
                                                {{ $transaction->status }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-4 text-center text-gray-500 dark:text-gray-400">Không có giao dịch nào gần đây.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-8">
                    <!-- VPS Status -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Trạng Thái VPS</h3>
                        <div class="space-y-4">
                        @forelse($vpsStatuses as $vps)
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-4 last:border-b-0">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $vps['name'] }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $vps['ip_address'] }}</span>
                                </div>
                                <div class="space-y-2">
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">CPU</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">{{$vps['cpu_usage']}}%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-blue-500 h-2 rounded-full" style="width:{{$vps['cpu_usage']}}%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">RAM</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">{{$vps['ram_usage']}}%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-green-500 h-2 rounded-full" style="width:{{$vps['ram_usage']}}%"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between mb-1">
                                            <span class="text-xs text-gray-600 dark:text-gray-400">Disk</span>
                                            <span class="text-xs text-gray-600 dark:text-gray-400">{{$vps['disk_usage']}}%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-red-500 h-2 rounded-full" style="width:{{$vps['disk_usage']}}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 dark:text-gray-400">Không có VPS nào đang hoạt động.</p>
                        @endforelse
                        </div>
                    </div>
                    <!-- Quick Actions -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Hành Động Nhanh</h3>
                        <div class="space-y-3">
                            <a href="{{ route('admin.streams') }}" class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-md font-medium transition-colors duration-200">
                                🎬 Quản lý Streams
                            </a>
                            <a href="{{ route('admin.users') }}" class="block w-full text-center bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-md font-medium transition-colors duration-200">
                                👥 Quản lý Users
                            </a>
                            <a href="{{ route('admin.transactions') }}" class="block w-full text-center bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-md font-medium transition-colors duration-200">
                                💰 Quản lý Giao dịch
                            </a>
                            <a href="{{ route('admin.settings') }}" class="block w-full text-center bg-purple-600 hover:bg-purple-700 text-white px-4 py-3 rounded-md font-medium transition-colors duration-200">
                                ⚙️ Cài đặt
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
