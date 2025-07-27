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
        <div class="flex items-center space-x-4">
            <div class="w-12 h-12 <?php echo e(auth()->user()->isAdmin() ? 'bg-red-500' : 'bg-blue-500'); ?> rounded-full flex items-center justify-center">
                <span class="text-xl font-bold text-white"><?php echo e(substr(Auth::user()->name, 0, 1)); ?></span>
            </div>
            <div>
                <h2 class="font-semibold text-2xl text-gray-800 dark:text-gray-200 leading-tight">
                    Hồ sơ cá nhân
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Quản lý thông tin tài khoản và cài đặt bảo mật của bạn
                </p>
            </div>
        </div>
     <?php $__env->endSlot(); ?>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- User Info Card -->
            <div class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl overflow-hidden mb-8">
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 px-6 py-8">
                    <div class="flex items-center space-x-6">
                        <div class="w-20 h-20 bg-white rounded-full flex items-center justify-center shadow-lg">
                            <span class="text-2xl font-bold text-blue-600"><?php echo e(substr(Auth::user()->name, 0, 1)); ?></span>
                        </div>
                        <div class="text-white">
                            <h1 class="text-2xl font-bold"><?php echo e(Auth::user()->name); ?></h1>
                            <p class="text-blue-100"><?php echo e(Auth::user()->email); ?></p>
                            <div class="flex items-center mt-2">
                                <?php if(auth()->user()->isAdmin()): ?>
                                    <span class="px-3 py-1 bg-red-600 text-white text-xs rounded-full font-semibold">
                                        🛡️ Quản trị viên
                                    </span>
                                <?php else: ?>
                                    <span class="px-3 py-1 bg-green-600 text-white text-xs rounded-full font-semibold">
                                        👤 Người dùng
                                    </span>
                                <?php endif; ?>
                                <span class="ml-4 px-3 py-1 bg-white/20 text-white text-xs rounded-full">
                                    📅 Tham gia <?php echo e(Auth::user()->created_at->format('d/m/Y')); ?>

                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Profile Information -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Thông tin cá nhân</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <?php echo $__env->make('profile.partials.update-profile-information-form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        </div>
                    </div>

                    <!-- Password Security -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Bảo mật mật khẩu</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <?php echo $__env->make('profile.partials.update-password-form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl overflow-hidden border border-red-200 dark:border-red-800">
                        <div class="px-6 py-4 bg-red-50 dark:bg-red-900/20 border-b border-red-200 dark:border-red-800">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <h3 class="text-lg font-semibold text-red-900 dark:text-red-100">Vùng nguy hiểm</h3>
                            </div>
                        </div>
                        <div class="p-6">
                            <?php echo $__env->make('profile.partials.delete-user-form', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    
                    <!-- Account Stats -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Thống kê tài khoản
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Streams</span>
                                </div>
                                <span class="font-semibold text-blue-600 dark:text-blue-400"><?php echo e(auth()->user()->streamConfigurations()->count()); ?></span>
                            </div>

                            <div class="flex items-center justify-between p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l6 6m0 0l-6-6m6 6V9a6 6 0 00-6-6H6a2 2 0 00-2 2v3"/>
                                    </svg>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Files</span>
                                </div>
                                <span class="font-semibold text-green-600 dark:text-green-400"><?php echo e(auth()->user()->files()->count()); ?></span>
                            </div>

                            <?php
                                $activeSubscription = auth()->user()->subscriptions()->where('status', 'ACTIVE')->with('servicePackage')->first();
                            ?>
                            <div class="flex items-center justify-between p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                    <span class="text-sm text-gray-700 dark:text-gray-300">Gói hiện tại</span>
                                </div>
                                <span class="font-semibold text-purple-600 dark:text-purple-400 text-xs">
                                    <?php echo e(auth()->user()->getSubscriptionShortName()); ?>

                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Thao tác nhanh
                        </h3>
                        <div class="space-y-3">
                            <a href="<?php echo e(route('dashboard')); ?>" class="block w-full px-4 py-2 bg-blue-100 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded-lg hover:bg-blue-200 dark:hover:bg-blue-900/40 transition-colors duration-200 text-center text-sm font-medium">
                                📊 Về Dashboard
                            </a>
                            <?php if(!auth()->user()->isAdmin()): ?>
                            <a href="<?php echo e(route('user.streams')); ?>" class="block w-full px-4 py-2 bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-300 rounded-lg hover:bg-green-200 dark:hover:bg-green-900/40 transition-colors duration-200 text-center text-sm font-medium">
                                🎥 Quản lý Streams
                            </a>
                            <a href="<?php echo e(route('services')); ?>" class="block w-full px-4 py-2 bg-purple-100 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300 rounded-lg hover:bg-purple-200 dark:hover:bg-purple-900/40 transition-colors duration-200 text-center text-sm font-medium">
                                💳 Gói Dịch Vụ
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Security Tips -->
                    <div class="bg-gradient-to-br from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 border border-yellow-200 dark:border-yellow-700 rounded-xl p-6">
                        <h3 class="text-lg font-semibold text-yellow-800 dark:text-yellow-200 mb-3 flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                            </svg>
                            Mẹo bảo mật
                        </h3>
                        <ul class="space-y-2 text-sm text-yellow-700 dark:text-yellow-300">
                            <li class="flex items-start space-x-2">
                                <span class="text-yellow-500 mt-0.5">•</span>
                                <span>Sử dụng mật khẩu mạnh với ít nhất 8 ký tự</span>
                            </li>
                            <li class="flex items-start space-x-2">
                                <span class="text-yellow-500 mt-0.5">•</span>
                                <span>Cập nhật thông tin Telegram để nhận thông báo</span>
                            </li>
                            <li class="flex items-start space-x-2">
                                <span class="text-yellow-500 mt-0.5">•</span>
                                <span>Không chia sẻ thông tin đăng nhập với ai</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
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
<?php /**PATH D:\laragon\www\ezstream\resources\views\profile\edit.blade.php ENDPATH**/ ?>