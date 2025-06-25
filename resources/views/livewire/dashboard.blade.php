<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Bảng điều khiển') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto">
            <!-- Welcome Section -->
            @if(Auth::user()->isAdmin())
                <div class="bg-gradient-to-r from-purple-500 to-indigo-600 rounded-xl p-8 text-white mb-8">
                    <h1 class="text-3xl font-bold mb-2">Chào mừng Admin {{ Auth::user()->name }}! 👑</h1>
                    <p class="text-purple-100">Bạn có quyền truy cập đầy đủ vào tất cả tính năng của hệ thống</p>
                </div>
            @else
                <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl p-8 text-white mb-8">
                    <h1 class="text-3xl font-bold mb-2">Chào mừng trở lại, {{ Auth::user()->name }}! 👋</h1>
                    <p class="text-blue-100">Quản lý stream và dịch vụ của bạn một cách dễ dàng</p>
                </div>
            @endif

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Streams Card -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Luồng đang hoạt động</p>
                            <p class="text-3xl font-bold text-gray-900">{{ $streamCount ?? 0 }}</p>
                            <p class="text-sm text-green-600 mt-1">
                                <svg class="w-4 h-4 inline" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                </svg>
                                Hoạt động tốt
                            </p>
                        </div>
                        <div class="bg-blue-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Storage Card -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Dung lượng sử dụng</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $storageUsedFormatted ?? '0 B' }}</p>
                            <p class="text-sm text-gray-500 mt-1">Còn trống nhiều</p>
                        </div>
                        <div class="bg-green-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2m-9 0h10m-10 0a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Package Card -->
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Gói hiện tại</p>
                            <p class="text-2xl font-bold text-gray-900">
                                @if($activeSubscription)
                                    {{ $activeSubscription->servicePackage->name }}
                                @else
                                    Chưa có gói
                                @endif
                            </p>
                            <p class="text-sm text-purple-600 mt-1">
                                @if($activeSubscription)
                                    Đang hoạt động
                                @else
                                    <a href="{{ route('billing.manager') }}" class="hover:underline">Chọn gói ngay →</a>
                                @endif
                            </p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Truy cập nhanh</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="{{ route('user.streams') }}" class="group bg-gradient-to-br from-blue-50 to-blue-100 hover:from-blue-100 hover:to-blue-200 p-6 rounded-lg transition-all duration-200 border border-blue-200">
                        <div class="text-center">
                            <div class="bg-blue-500 text-white rounded-full p-3 w-12 h-12 mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900">Quản Lý Stream</h4>
                            <p class="text-sm text-gray-600 mt-1">Tạo và quản lý luồng</p>
                        </div>
                    </a>

                    <a href="{{ route('file.manager') }}" class="group bg-gradient-to-br from-green-50 to-green-100 hover:from-green-100 hover:to-green-200 p-6 rounded-lg transition-all duration-200 border border-green-200">
                        <div class="text-center">
                            <div class="bg-green-500 text-white rounded-full p-3 w-12 h-12 mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900">Quản Lý File</h4>
                            <p class="text-sm text-gray-600 mt-1">Upload và quản lý video</p>
                        </div>
                    </a>

                    <a href="{{ route('billing.manager') }}" class="group bg-gradient-to-br from-purple-50 to-purple-100 hover:from-purple-100 hover:to-purple-200 p-6 rounded-lg transition-all duration-200 border border-purple-200">
                        <div class="text-center">
                            <div class="bg-purple-500 text-white rounded-full p-3 w-12 h-12 mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900">Gói & Thanh toán</h4>
                            <p class="text-sm text-gray-600 mt-1">Quản lý gói dịch vụ</p>
                        </div>
                    </a>

                    <a href="{{ route('profile.edit') }}" class="group bg-gradient-to-br from-orange-50 to-orange-100 hover:from-orange-100 hover:to-orange-200 p-6 rounded-lg transition-all duration-200 border border-orange-200">
                        <div class="text-center">
                            <div class="bg-orange-500 text-white rounded-full p-3 w-12 h-12 mx-auto mb-3 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <h4 class="font-semibold text-gray-900">Hồ Sơ</h4>
                            <p class="text-sm text-gray-600 mt-1">Cập nhật thông tin</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
