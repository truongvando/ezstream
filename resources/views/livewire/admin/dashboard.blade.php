<div class="space-y-8" wire:poll.15s>
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Admin Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tổng quan hệ thống ngày {{ now()->format('d/m/Y') }}</p>
        </div>
        <div class="flex items-center space-x-2">
            <a href="{{ route('admin.settings') }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-800 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition ease-in-out duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Cài đặt
            </a>
            <button wire:click="$refresh" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition ease-in-out duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4l7 7m9 9l-7-7" />
                </svg>
                Làm mới
            </button>
        </div>
    </div>

    <!-- Stat Widgets -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-6">
        <!-- Helper function to create stat cards -->
        @php
        function renderStatCard($title, $value, $icon, $color, $link = '#', $tooltip = '') {
            $baseColor = "text-{$color}-600 dark:text-{$color}-400";
            $bgColor = "bg-{$color}-100 dark:bg-{$color}-900/50";
            $ringColor = "ring-{$color}-500";
            return <<<HTML
            <a href="{$link}" class="bg-white dark:bg-gray-800 p-5 rounded-xl shadow-md flex items-center space-x-4 hover:shadow-lg transition-shadow duration-300 relative group" title="{$tooltip}">
                <div class="{$bgColor} p-3 rounded-full">
                    <svg class="w-6 h-6 {$baseColor}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {$icon}
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{$title}</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{$value}</p>
                </div>
            </a>
HTML;
        }
        @endphp

        {!! renderStatCard('Doanh Thu', number_format($stats['total_revenue'], 0, ',', '.') . ' VNĐ', '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v.01"/>', 'yellow', route('admin.transactions'), 'Tổng doanh thu đã hoàn thành') !!}
        {!! renderStatCard('Chờ Xử Lý', $stats['pending_transactions'], '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />', 'orange', route('admin.transactions'), 'Số giao dịch đang chờ xác nhận') !!}
        {!! renderStatCard('Tổng Users', $stats['total_users'], '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>', 'blue', route('admin.users'), 'Tổng số người dùng trong hệ thống') !!}
        {!! renderStatCard('User Mới (7d)', $stats['new_users_this_week'], '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />', 'teal', route('admin.users'), 'Số người dùng đăng ký trong 7 ngày qua') !!}
        {!! renderStatCard('Streams Chạy', $stats['active_streams'], '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>', 'green', route('admin.streams'), 'Số stream đang hoạt động') !!}
        {!! renderStatCard('Streams Lỗi', $stats['error_streams'], '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />', 'red', route('admin.streams'), 'Số stream đang gặp lỗi') !!}
        {!! renderStatCard('VPS Hoạt Động', $stats['active_vps_servers'], '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>', 'purple', route('admin.vps-servers'), 'Số VPS đang hoạt động') !!}
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Chart and Recent Transactions -->
        <div class="lg:col-span-2 space-y-8">
            <!-- Revenue Chart -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-md">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Doanh thu 30 ngày qua</h3>
                 <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Biểu đồ thể hiện tổng doanh thu mỗi ngày.</p>
                <div style="height: 300px;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-md">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Giao Dịch Gần Đây</h3>
                    <a href="{{ route('admin.transactions') }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Xem tất cả</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider py-3 px-4">User</th>
                                <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider py-3 px-4">Số tiền</th>
                                <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider py-3 px-4">Gói</th>
                                <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider py-3 px-4">Trạng Thái</th>
                                <th class="text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider py-3 px-4">Thời gian</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($recentTransactions as $transaction)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-3">
                                        <img class="h-8 w-8 rounded-full object-cover" src="{{ $transaction->user->gravatar() }}" alt="{{ $transaction->user->name }}">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $transaction->user->name }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $transaction->user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-sm font-medium text-gray-900 dark:text-white">{{ number_format($transaction->amount, 0, ',', '.') }} VNĐ</td>
                                <td class="py-3 px-4 text-sm text-gray-500 dark:text-gray-300">{{ optional($transaction->servicePackage)->name ?? 'N/A' }}</td>
                                <td class="py-3 px-4">
                                    <x-dynamic-component :component="'transaction-status-badge'" :status="$transaction->status" />
                                </td>
                                <td class="py-3 px-4 text-sm text-gray-500 dark:text-gray-400" title="{{ $transaction->created_at->format('H:i:s d/m/Y') }}">
                                    {{ $transaction->created_at->diffForHumans() }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-gray-500 dark:text-gray-400">Không có giao dịch nào gần đây.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column: Recent Streams -->
        <div class="space-y-8">
            <!-- Recent Streams -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-md">
                 <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Streams Gần Đây</h3>
                    <a href="{{ route('admin.streams') }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Xem tất cả</a>
                </div>
                <div class="space-y-4">
                    @forelse($recentStreams as $stream)
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <x-dynamic-component :component="'stream-status-icon'" :status="$stream->status" class="h-8 w-8" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate dark:text-white" title="{{ $stream->title }}">
                                    {{ $stream->title }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    by {{ $stream->user->name }} on {{ optional($stream->vpsServer)->name ?? 'N/A' }}
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <x-dynamic-component :component="'stream-status-badge'" :status="$stream->status" />
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">Không có stream nào gần đây.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('livewire:load', function () {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    let chart;

    function renderChart(data) {
        if (chart) {
            chart.destroy();
        }

        const chartConfig = {
            type: 'line',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: '#fff',
                        titleColor: '#333',
                        bodyColor: '#666',
                        borderColor: '#ddd',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: document.body.classList.contains('dark') ? '#9CA3AF' : '#6B7281',
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: document.body.classList.contains('dark') ? '#374151' : '#E5E7EB',
                        },
                        ticks: {
                           color: document.body.classList.contains('dark') ? '#9CA3AF' : '#6B7281',
                           callback: function(value, index, values) {
                                if (value >= 1000000) {
                                    return (value / 1000000) + ' Tr';
                                }
                                if (value >= 1000) {
                                    return (value / 1000) + ' K';
                                }
                                return value;
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        };

        chart = new Chart(ctx, chartConfig);
    }
    
    renderChart(@json($chartData));

    Livewire.on('refresh', data => {
        renderChart(data.chartData);
    });
});
</script>
