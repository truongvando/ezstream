<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('header', null, []); ?> 
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-3xl text-gray-800 dark:text-gray-200 leading-tight">
                    <?php if(auth()->user()->isAdmin()): ?>
                        🛡️ Admin Dashboard
                    <?php else: ?>
                        📊 Bảng điều khiển
                    <?php endif; ?>
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Chào mừng trở lại, <?php echo e(Auth::user()->name); ?>! 
                    <?php echo e(now()->hour < 12 ? 'Chúc bạn buổi sáng tốt lành!' : (now()->hour < 18 ? 'Chúc bạn buổi chiều vui vẻ!' : 'Chúc bạn buổi tối thư giãn!')); ?>

                </p>
            </div>
            <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                <p><?php echo e(now()->format('l, d/m/Y')); ?></p>
                <p><?php echo e(now()->format('H:i')); ?></p>
            </div>
        </div>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <?php if(auth()->user()->isAdmin()): ?>
                <?php
$__split = function ($name, $params = []) {
    return [$name, $params];
};
[$__name, $__params] = $__split('admin.dashboard');

$__html = app('livewire')->mount($__name, $__params, 'lw-2202449897-0', $__slots ?? [], get_defined_vars());

echo $__html;

unset($__html);
unset($__name);
unset($__params);
unset($__split);
if (isset($__slots)) unset($__slots);
?>
            <?php else: ?>
                <!-- Pending Payment Alert -->
                <?php
                    $pendingSubscription = auth()->user()->subscriptions()->where('status', 'PENDING_PAYMENT')->with('servicePackage')->first();
                ?>
                <?php if($pendingSubscription): ?>
                    <div class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white rounded-xl shadow-lg p-6 mb-8">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center">
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-bold">⏳ Có gói dịch vụ chờ thanh toán</h3>
                                <p class="text-white/90">
                                    <strong><?php echo e($pendingSubscription->servicePackage->name); ?></strong> - 
                                    Vui lòng hoàn tất thanh toán để kích hoạt dịch vụ.
                                </p>
                            </div>
                            <a href="<?php echo e(route('services')); ?>" class="bg-white text-orange-600 px-6 py-2 rounded-lg font-semibold hover:bg-gray-100 transition-colors duration-200">
                                Thanh toán ngay →
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Welcome Card -->
                <div class="bg-gradient-to-br from-blue-500 via-purple-600 to-indigo-700 rounded-2xl shadow-xl text-white p-8 mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold mb-2">Xin chào, <?php echo e(Auth::user()->name); ?>! 👋</h1>
                            <p class="text-blue-100 text-lg">Quản lý streams và dịch vụ STREAM của bạn tại đây</p>
                        </div>
                        <div class="hidden md:block">
                            <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center">
                                <span class="text-3xl font-bold"><?php echo e(substr(Auth::user()->name, 0, 1)); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- My Streams -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl rounded-2xl transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="p-4 rounded-2xl bg-gradient-to-br from-indigo-400 to-indigo-600">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4 flex-1">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Tổng Streams</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        <?php echo e(auth()->user()->streamConfigurations()->count()); ?>

                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">📺 Đã tạo</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-indigo-50 dark:bg-indigo-900/20 px-6 py-3">
                            <a href="<?php echo e(route('user.streams')); ?>" class="text-indigo-600 dark:text-indigo-400 text-sm font-semibold hover:text-indigo-800 dark:hover:text-indigo-300">
                                Quản lý streams →
                            </a>
                        </div>
                    </div>

                    <!-- Active Streams -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl rounded-2xl transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="p-4 rounded-2xl bg-gradient-to-br from-green-400 to-green-600">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4 flex-1">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Đang hoạt động</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        <?php echo e(auth()->user()->streamConfigurations()->where('status', 'ACTIVE')->count()); ?>

                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">🟢 Live</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/20 px-6 py-3">
                            <span class="text-green-600 dark:text-green-400 text-sm font-semibold">
                                Streams đang phát →
                            </span>
                        </div>
                    </div>

                    <!-- Current Package -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl rounded-2xl transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="p-4 rounded-2xl bg-gradient-to-br from-blue-400 to-blue-600">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                    </svg>
                                </div>
                                <div class="ml-4 flex-1">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Gói dịch vụ</p>
                                    <?php
                                        $activeSubscription = auth()->user()->subscriptions()->where('status', 'ACTIVE')->with('servicePackage')->first();
                                    ?>
                                                                <p class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                <?php echo e(auth()->user()->getSubscriptionDisplayName()); ?>

                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <?php if(auth()->user()->isAdmin()): ?>
                                    👑 Quản trị viên
                                <?php elseif(auth()->user()->getTotalAllowedStreams() > 0): ?>
                                    💎 Đang hoạt động (<?php echo e(auth()->user()->getTotalAllowedStreams()); ?> streams)
                                <?php else: ?>
                                    ⚠️ Chưa đăng ký
                                <?php endif; ?>
                            </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/20 px-6 py-3">
                            <a href="<?php echo e(route('services')); ?>" class="text-blue-600 dark:text-blue-400 text-sm font-semibold hover:text-blue-800 dark:hover:text-blue-300">
                                <?php echo e($activeSubscription ? 'Quản lý gói' : 'Chọn gói'); ?> →
                            </a>
                        </div>
                    </div>

                    <!-- Storage Usage -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl rounded-2xl transition-all duration-300 hover:shadow-2xl hover:-translate-y-1">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="p-4 rounded-2xl bg-gradient-to-br from-purple-400 to-purple-600">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4 flex-1">
                                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Files đã tải</p>
                                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                                        <?php echo e(auth()->user()->files()->count()); ?>

                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">📁 Tệp tin</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-purple-50 dark:bg-purple-900/20 px-6 py-3">
                            <a href="<?php echo e(route('files.index')); ?>" class="text-purple-600 dark:text-purple-400 text-sm font-semibold hover:text-purple-800 dark:hover:text-purple-300">
                                Quản lý files →
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl rounded-2xl mb-8">
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Thao tác nhanh
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <a href="<?php echo e(route('user.streams')); ?>" class="group bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white rounded-xl p-6 transition-all duration-300 hover:shadow-lg hover:scale-105">
                                <div class="flex items-center space-x-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <div>
                                        <h4 class="font-semibold">Tạo Stream Mới</h4>
                                        <p class="text-sm text-indigo-100">Bắt đầu streaming ngay</p>
                                    </div>
                                </div>
                            </a>

                            <?php if(!$activeSubscription): ?>
                            <a href="<?php echo e(route('services')); ?>" class="group bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl p-6 transition-all duration-300 hover:shadow-lg hover:scale-105">
                                <div class="flex items-center space-x-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    <div>
                                        <h4 class="font-semibold">Chọn Gói Dịch Vụ</h4>
                                        <p class="text-sm text-green-100">Nâng cấp tài khoản</p>
                                    </div>
                                </div>
                            </a>
                            <?php endif; ?>

                            <a href="<?php echo e(route('files.index')); ?>" class="group bg-gradient-to-r from-blue-500 to-cyan-600 hover:from-blue-600 hover:to-cyan-700 text-white rounded-xl p-6 transition-all duration-300 hover:shadow-lg hover:scale-105">
                                <div class="flex items-center space-x-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    <div>
                                        <h4 class="font-semibold">Upload File</h4>
                                        <p class="text-sm text-blue-100">Tải lên video mới</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Streams -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl rounded-2xl">
                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Streams gần đây
                        </h3>
                    </div>
                    <div class="p-6">
                        <?php
                            $recentStreams = auth()->user()->streamConfigurations()->with('vpsServer')->latest()->take(5)->get();
                        ?>
                        <?php if($recentStreams->count() > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stream</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Cập nhật</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php $__currentLoopData = $recentStreams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stream): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-200">
                                        <td class="px-4 py-4">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                    </svg>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100"><?php echo e($stream->title); ?></div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">📍 <?php echo e($stream->vpsServer->name ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                                                <?php switch($stream->status):
                                                    case ('ACTIVE'): ?> bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 <?php break; ?>
                                                    <?php case ('INACTIVE'): ?> bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400 <?php break; ?>
                                                    <?php case ('ERROR'): ?> bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400 <?php break; ?>
                                                    <?php default: ?> bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400
                                                <?php endswitch; ?>
                                            ">
                                                <?php switch($stream->status):
                                                    case ('ACTIVE'): ?> 🟢 Đang phát <?php break; ?>
                                                    <?php case ('INACTIVE'): ?> ⚪ Không hoạt động <?php break; ?>
                                                    <?php case ('ERROR'): ?> 🔴 Lỗi <?php break; ?>
                                                    <?php default: ?> 🔵 <?php echo e($stream->status); ?>

                                                <?php endswitch; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo e($stream->updated_at->diffForHumans()); ?>

                                        </td>
                                        <td class="px-4 py-4">
                                            <a href="<?php echo e(route('user.streams')); ?>" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 text-sm font-medium">
                                                Quản lý →
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Chưa có stream nào</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Bắt đầu bằng cách tạo stream đầu tiên của bạn.</p>
                            <div class="mt-6">
                                <a href="<?php echo e(route('user.streams')); ?>" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Tạo Stream Mới
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>


 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH D:\laragon\www\ezstream\resources\views/dashboard.blade.php ENDPATH**/ ?>