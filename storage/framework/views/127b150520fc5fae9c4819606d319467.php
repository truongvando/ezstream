<div>
    <div class="py-4 sm:py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="bg-gradient-to-br from-blue-500 via-purple-600 to-indigo-700 rounded-2xl shadow-xl text-white p-6 sm:p-8 mb-6 sm:mb-8">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                    <div class="flex-1">
                        <div class="flex items-center space-x-3 mb-3">
                            <div class="bg-white/20 p-2 rounded-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <h1 class="text-2xl sm:text-3xl font-bold">Bảng điều khiển</h1>
                        </div>
                        <p class="text-blue-100 text-base sm:text-lg">
                            Chào mừng trở lại, <span class="font-semibold"><?php echo e(Auth::user()->name); ?></span>!
                            <?php echo e(now()->hour < 12 ? 'Chúc bạn buổi sáng tốt lành!' : (now()->hour < 18 ? 'Chúc bạn buổi chiều vui vẻ!' : 'Chúc bạn buổi tối thư giãn!')); ?>

                        </p>
                        <p class="text-blue-200 text-sm mt-2">Quản lý streams và dịch vụ EZSTREAM của bạn tại đây</p>
                    </div>
                    <div class="flex-shrink-0 self-center sm:self-auto">
                        <div class="flex flex-col items-center space-y-2">
                            <div class="w-16 h-16 sm:w-20 sm:h-20 bg-white/20 rounded-full flex items-center justify-center">
                                <span class="text-2xl sm:text-3xl font-bold"><?php echo e(substr(Auth::user()->name, 0, 1)); ?></span>
                            </div>
                            <div class="text-center">
                                <p class="text-xs text-blue-200"><?php echo e(now()->format('l')); ?></p>
                                <p class="text-sm font-semibold"><?php echo e(now()->format('d/m/Y')); ?></p>
                                <p class="text-xs text-blue-200"><?php echo e(now()->format('H:i')); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
                <!-- Streams Card -->
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Tổng Streams</p>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-900"><?php echo e($streamCount ?? 0); ?></p>
                            <p class="text-xs sm:text-sm text-green-600 mt-1 flex items-center">
                                <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                </svg>
                                📺 Đã tạo
                            </p>
                        </div>
                        <div class="bg-blue-100 p-2 sm:p-3 rounded-full flex-shrink-0">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Storage Card -->
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Đang hoạt động</p>
                            <p class="text-2xl sm:text-3xl font-bold text-gray-900"><?php echo e(auth()->user()->streamConfigurations()->whereIn('status', ['STREAMING', 'STARTING'])->count()); ?></p>
                            <p class="text-xs sm:text-sm text-green-600 mt-1">🟢 Live</p>
                        </div>
                        <div class="bg-green-100 p-2 sm:p-3 rounded-full flex-shrink-0">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1m4 0h1m-6 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Package Card -->
                <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs sm:text-sm font-medium text-gray-600 truncate">Gói dịch vụ</p>
                            <p class="text-lg sm:text-xl font-bold text-gray-900 truncate">
                                <?php echo e(auth()->user()->getSubscriptionDisplayName()); ?>

                            </p>
                            <p class="text-xs sm:text-sm text-purple-600 mt-1">
                                <?php if(auth()->user()->isAdmin()): ?>
                                    👑 Quản trị viên
                                <?php elseif(auth()->user()->getTotalAllowedStreams() > 0): ?>
                                    💎 Đang hoạt động (<?php echo e(auth()->user()->getTotalAllowedStreams()); ?> streams)
                                <?php else: ?>
                                    <a href="<?php echo e(route('services')); ?>" class="hover:underline">Chọn gói ngay →</a>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="bg-purple-100 p-2 sm:p-3 rounded-full flex-shrink-0">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-lg p-4 sm:p-6 border border-gray-100">
                <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Thao tác nhanh
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <a href="<?php echo e(route('user.streams')); ?>" class="group bg-gradient-to-br from-blue-50 to-blue-100 hover:from-blue-100 hover:to-blue-200 p-4 sm:p-6 rounded-lg transition-all duration-200 border border-blue-200 hover:shadow-md">
                        <div class="text-center">
                            <div class="bg-blue-500 text-white rounded-full p-2 sm:p-3 w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-2 sm:mb-3 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900 text-sm sm:text-base">Tạo Stream Mới</h4>
                            <p class="text-xs sm:text-sm text-gray-600 mt-1">Bắt đầu streaming ngay</p>
                        </div>
                    </a>

                    <a href="<?php echo e(route('files.index')); ?>" class="group bg-gradient-to-br from-green-50 to-green-100 hover:from-green-100 hover:to-green-200 p-4 sm:p-6 rounded-lg transition-all duration-200 border border-green-200 hover:shadow-md">
                        <div class="text-center">
                            <div class="bg-green-500 text-white rounded-full p-2 sm:p-3 w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-2 sm:mb-3 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900 text-sm sm:text-base">Upload File</h4>
                            <p class="text-xs sm:text-sm text-gray-600 mt-1">Tải lên video mới</p>
                        </div>
                    </a>

                    <a href="<?php echo e(route('services')); ?>" class="group bg-gradient-to-br from-purple-50 to-purple-100 hover:from-purple-100 hover:to-purple-200 p-4 sm:p-6 rounded-lg transition-all duration-200 border border-purple-200 hover:shadow-md">
                        <div class="text-center">
                            <div class="bg-purple-500 text-white rounded-full p-2 sm:p-3 w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-2 sm:mb-3 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900 text-sm sm:text-base">Gói Dịch Vụ</h4>
                            <p class="text-xs sm:text-sm text-gray-600 mt-1">Chọn gói & thanh toán</p>
                        </div>
                    </a>

                    <a href="<?php echo e(route('profile.edit')); ?>" class="group bg-gradient-to-br from-orange-50 to-orange-100 hover:from-orange-100 hover:to-orange-200 p-4 sm:p-6 rounded-lg transition-all duration-200 border border-orange-200 hover:shadow-md">
                        <div class="text-center">
                            <div class="bg-orange-500 text-white rounded-full p-2 sm:p-3 w-10 h-10 sm:w-12 sm:h-12 mx-auto mb-2 sm:mb-3 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900 text-sm sm:text-base">Hồ Sơ</h4>
                            <p class="text-xs sm:text-sm text-gray-600 mt-1">Cập nhật thông tin</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views\livewire\dashboard.blade.php ENDPATH**/ ?>