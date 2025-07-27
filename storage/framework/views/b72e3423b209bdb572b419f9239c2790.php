<div>
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
        <div class="p-6 lg:p-8 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <h1 class="text-2xl font-medium text-gray-900 dark:text-white">
                Welcome to your Admin Dashboard!
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tổng quan hệ thống ngày <?php echo e(now()->format('d/m/Y')); ?></p>
        </div>

        <!-- Stat Widgets -->
        <div class="p-6 lg:p-8 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-7 gap-6">
            <!-- Helper function to create stat cards -->
            <?php
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
            ?>

            <?php echo renderStatCard('Doanh Thu', number_format($stats['total_revenue'], 0, ',', '.') . ' VNĐ', '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v.01"/>', 'yellow', route('admin.transactions'), 'Tổng doanh thu đã hoàn thành'); ?>

            <?php echo renderStatCard('Chờ Xử Lý', $stats['pending_transactions'], '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />', 'orange', route('admin.transactions'), 'Số giao dịch đang chờ xác nhận'); ?>

            <?php echo renderStatCard('Tổng Users', $stats['total_users'], '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>', 'blue', route('admin.users'), 'Tổng số người dùng trong hệ thống'); ?>

            <?php echo renderStatCard('User Mới (7d)', $stats['new_users_this_week'], '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />', 'teal', route('admin.users'), 'Số người dùng đăng ký trong 7 ngày qua'); ?>

            <?php echo renderStatCard('Streams Chạy', $stats['active_streams'], '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>', 'green', route('admin.streams'), 'Số stream đang hoạt động'); ?>

            <?php echo renderStatCard('Streams Lỗi', $stats['error_streams'], '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />', 'red', route('admin.streams'), 'Số stream đang gặp lỗi'); ?>

            <?php echo renderStatCard('VPS Hoạt Động', $stats['active_vps_servers'], '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>', 'purple', route('admin.vps-servers'), 'Số VPS đang hoạt động'); ?>

        </div>

        <!-- Main Content Grid -->
        <div class="p-6 lg:p-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
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
                        <a href="<?php echo e(route('admin.transactions')); ?>" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Xem tất cả</a>
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
                            <?php $__empty_1 = true; $__currentLoopData = $recentTransactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="py-3 px-4">
                                        <div class="flex items-center space-x-3">
                                            <img class="h-8 w-8 rounded-full object-cover" src="<?php echo e($transaction->user->gravatar()); ?>" alt="<?php echo e($transaction->user->name); ?>">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo e($transaction->user->name); ?></div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($transaction->user->email); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-sm font-medium text-gray-900 dark:text-white"><?php echo e(number_format($transaction->amount, 0, ',', '.')); ?> VNĐ</td>
                                    <td class="py-3 px-4 text-sm text-gray-500 dark:text-gray-300"><?php echo e(optional($transaction->servicePackage)->name ?? 'N/A'); ?></td>
                                    <td class="py-3 px-4">
                                        <?php if (isset($component)) { $__componentOriginal511d4862ff04963c3c16115c05a86a9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal511d4862ff04963c3c16115c05a86a9d = $attributes; } ?>
<?php $component = Illuminate\View\DynamicComponent::resolve(['component' => 'transaction-status-badge'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dynamic-component'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\DynamicComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => $transaction->status]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal511d4862ff04963c3c16115c05a86a9d)): ?>
<?php $attributes = $__attributesOriginal511d4862ff04963c3c16115c05a86a9d; ?>
<?php unset($__attributesOriginal511d4862ff04963c3c16115c05a86a9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal511d4862ff04963c3c16115c05a86a9d)): ?>
<?php $component = $__componentOriginal511d4862ff04963c3c16115c05a86a9d; ?>
<?php unset($__componentOriginal511d4862ff04963c3c16115c05a86a9d); ?>
<?php endif; ?>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-500 dark:text-gray-400" title="<?php echo e($transaction->created_at->format('H:i:s d/m/Y')); ?>">
                                        <?php echo e($transaction->created_at->diffForHumans()); ?>

                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="5" class="py-6 text-center text-gray-500 dark:text-gray-400">Không có giao dịch nào gần đây.</td>
                                </tr>
                            <?php endif; ?>
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
                        <a href="<?php echo e(route('admin.streams')); ?>" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">Xem tất cả</a>
                    </div>
                    <div class="space-y-4">
                        <?php $__empty_1 = true; $__currentLoopData = $recentStreams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stream): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <div class="flex items-center space-x-4">
                                <div class="flex-shrink-0">
                                    <?php if (isset($component)) { $__componentOriginal511d4862ff04963c3c16115c05a86a9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal511d4862ff04963c3c16115c05a86a9d = $attributes; } ?>
<?php $component = Illuminate\View\DynamicComponent::resolve(['component' => 'stream-status-icon'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dynamic-component'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\DynamicComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => $stream->status,'class' => 'h-8 w-8']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal511d4862ff04963c3c16115c05a86a9d)): ?>
<?php $attributes = $__attributesOriginal511d4862ff04963c3c16115c05a86a9d; ?>
<?php unset($__attributesOriginal511d4862ff04963c3c16115c05a86a9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal511d4862ff04963c3c16115c05a86a9d)): ?>
<?php $component = $__componentOriginal511d4862ff04963c3c16115c05a86a9d; ?>
<?php unset($__componentOriginal511d4862ff04963c3c16115c05a86a9d); ?>
<?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate dark:text-white" title="<?php echo e($stream->title); ?>">
                                        <?php echo e($stream->title); ?>

                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        by <?php echo e($stream->user->name); ?> on <?php echo e(optional($stream->vpsServer)->name ?? 'N/A'); ?>

                                    </p>
                                </div>
                                <div class="flex-shrink-0">
                                    <?php if (isset($component)) { $__componentOriginal511d4862ff04963c3c16115c05a86a9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal511d4862ff04963c3c16115c05a86a9d = $attributes; } ?>
<?php $component = Illuminate\View\DynamicComponent::resolve(['component' => 'stream-status-badge'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('dynamic-component'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\DynamicComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => $stream->status]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal511d4862ff04963c3c16115c05a86a9d)): ?>
<?php $attributes = $__attributesOriginal511d4862ff04963c3c16115c05a86a9d; ?>
<?php unset($__attributesOriginal511d4862ff04963c3c16115c05a86a9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal511d4862ff04963c3c16115c05a86a9d)): ?>
<?php $component = $__componentOriginal511d4862ff04963c3c16115c05a86a9d; ?>
<?php unset($__componentOriginal511d4862ff04963c3c16115c05a86a9d); ?>
<?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Không có stream nào gần đây.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Event Monitor -->
    <div class="mt-8">
        <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('admin.system-event-monitor', []);

$__html = app('livewire')->mount($__name, $__params, 'lw-1784371842-0', $__slots ?? [], get_defined_vars());

echo $__html;

unset($__html);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
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
        
        renderChart(<?php echo json_encode($chartData, 15, 512) ?>);

        Livewire.on('refresh', data => {
            renderChart(data.chartData);
        });
    });
    </script>
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views\livewire\admin\dashboard.blade.php ENDPATH**/ ?>