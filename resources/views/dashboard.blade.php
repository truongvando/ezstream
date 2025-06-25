<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-3xl text-gray-800 dark:text-gray-200 leading-tight">
                    @if(auth()->user()->isAdmin())
                        🛡️ Admin Dashboard
                    @else
                        📊 Bảng điều khiển
                    @endif
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Chào mừng trở lại, {{ Auth::user()->name }}! 
                    {{ now()->hour < 12 ? 'Chúc bạn buổi sáng tốt lành!' : (now()->hour < 18 ? 'Chúc bạn buổi chiều vui vẻ!' : 'Chúc bạn buổi tối thư giãn!') }}
                </p>
            </div>
            <div class="text-right text-sm text-gray-500 dark:text-gray-400">
                <p>{{ now()->format('l, d/m/Y') }}</p>
                <p>{{ now()->format('H:i') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(auth()->user()->isAdmin())
                @livewire('admin.dashboard')
            @else
                <!-- Pending Payment Alert -->
                @php
                    $pendingSubscription = auth()->user()->subscriptions()->where('status', 'PENDING_PAYMENT')->with('servicePackage')->first();
                @endphp
                @if($pendingSubscription)
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
                                    <strong>{{ $pendingSubscription->servicePackage->name }}</strong> - 
                                    Vui lòng hoàn tất thanh toán để kích hoạt dịch vụ.
                                </p>
                            </div>
                            <a href="{{ route('billing.manager') }}" class="bg-white text-orange-600 px-6 py-2 rounded-lg font-semibold hover:bg-gray-100 transition-colors duration-200">
                                Thanh toán ngay →
                            </a>
                        </div>
                    </div>
                @endif

                <!-- Welcome Card -->
                <div class="bg-gradient-to-br from-blue-500 via-purple-600 to-indigo-700 rounded-2xl shadow-xl text-white p-8 mb-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-3xl font-bold mb-2">Xin chào, {{ Auth::user()->name }}! 👋</h1>
                            <p class="text-blue-100 text-lg">Quản lý streams và dịch vụ VPS của bạn tại đây</p>
                        </div>
                        <div class="hidden md:block">
                            <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center">
                                <span class="text-3xl font-bold">{{ substr(Auth::user()->name, 0, 1) }}</span>
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
                                        {{ auth()->user()->streamConfigurations()->count() }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">📺 Đã tạo</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-indigo-50 dark:bg-indigo-900/20 px-6 py-3">
                            <a href="{{ route('user.streams') }}" class="text-indigo-600 dark:text-indigo-400 text-sm font-semibold hover:text-indigo-800 dark:hover:text-indigo-300">
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
                                        {{ auth()->user()->streamConfigurations()->where('status', 'ACTIVE')->count() }}
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
                                    @php
                                        $activeSubscription = auth()->user()->subscriptions()->where('status', 'ACTIVE')->with('servicePackage')->first();
                                    @endphp
                                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">
                                        {{ $activeSubscription ? $activeSubscription->servicePackage->name : 'Chưa có gói' }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $activeSubscription ? '💎 Đang hoạt động' : '⚠️ Chưa đăng ký' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-blue-50 dark:bg-blue-900/20 px-6 py-3">
                            <a href="{{ route('billing.manager') }}" class="text-blue-600 dark:text-blue-400 text-sm font-semibold hover:text-blue-800 dark:hover:text-blue-300">
                                {{ $activeSubscription ? 'Quản lý gói' : 'Chọn gói' }} →
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
                                        {{ auth()->user()->userFiles()->count() }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">📁 Tệp tin</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-purple-50 dark:bg-purple-900/20 px-6 py-3">
                            <a href="{{ route('file.manager') }}" class="text-purple-600 dark:text-purple-400 text-sm font-semibold hover:text-purple-800 dark:hover:text-purple-300">
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
                            <a href="{{ route('user.streams') }}" class="group bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white rounded-xl p-6 transition-all duration-300 hover:shadow-lg hover:scale-105">
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

                            @if(!$activeSubscription)
                            <a href="{{ route('billing.manager') }}" class="group bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-xl p-6 transition-all duration-300 hover:shadow-lg hover:scale-105">
                                <div class="flex items-center space-x-4">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                    <div>
                                        <h4 class="font-semibold">Chọn Gói Dịch Vụ</h4>
                                        <p class="text-sm text-green-100">Nâng cấp tài khoản</p>
                                    </div>
                                </div>
                            </a>
                            @endif

                            <a href="{{ route('file.manager') }}" class="group bg-gradient-to-r from-blue-500 to-cyan-600 hover:from-blue-600 hover:to-cyan-700 text-white rounded-xl p-6 transition-all duration-300 hover:shadow-lg hover:scale-105">
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
                        @php
                            $recentStreams = auth()->user()->streamConfigurations()->with('vpsServer')->latest()->take(5)->get();
                        @endphp
                        @if($recentStreams->count() > 0)
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
                                    @foreach($recentStreams as $stream)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-200">
                                        <td class="px-4 py-4">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-600 rounded-lg flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                                    </svg>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $stream->title }}</div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">📍 {{ $stream->vpsServer->name ?? 'N/A' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                                                @switch($stream->status)
                                                    @case('ACTIVE') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 @break
                                                    @case('INACTIVE') bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400 @break
                                                    @case('ERROR') bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400 @break
                                                    @default bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400
                                                @endswitch
                                            ">
                                                @switch($stream->status)
                                                    @case('ACTIVE') 🟢 Đang phát @break
                                                    @case('INACTIVE') ⚪ Không hoạt động @break
                                                    @case('ERROR') 🔴 Lỗi @break
                                                    @default 🔵 {{ $stream->status }}
                                                @endswitch
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $stream->updated_at->diffForHumans() }}
                                        </td>
                                        <td class="px-4 py-4">
                                            <a href="{{ route('user.streams') }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 text-sm font-medium">
                                                Quản lý →
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Chưa có stream nào</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Bắt đầu bằng cách tạo stream đầu tiên của bạn.</p>
                            <div class="mt-6">
                                <a href="{{ route('user.streams') }}" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Tạo Stream Mới
                                </a>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
