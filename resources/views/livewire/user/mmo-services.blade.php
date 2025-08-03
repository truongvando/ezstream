<div class="max-w-7xl mx-auto p-6">
    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
            <div class="flex">
                <div class="py-1">
                    <svg class="fill-current h-6 w-6 text-green-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-bold">Thành công!</p>
                    <p class="text-sm">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
            <div class="flex">
                <div class="py-1">
                    <svg class="fill-current h-6 w-6 text-red-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-bold">Lỗi!</p>
                    <p class="text-sm">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Header -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">🎮 Dịch vụ MMO</h1>
            <p class="text-gray-600 dark:text-gray-400">Các dịch vụ MMO chất lượng cao, giao hàng nhanh chóng</p>
            <div class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                💰 Số dư hiện tại: <span class="font-bold text-green-600">${{ number_format(auth()->user()->balance, 2) }}</span>
            </div>
        </div>
    </div>

    <!-- Featured Services -->
    @if($featuredServices->count() > 0)
        <div class="mb-8">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">⭐ Dịch vụ nổi bật</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($featuredServices as $service)
                    <div class="bg-gradient-to-br from-purple-50 to-blue-50 dark:from-purple-900 dark:to-blue-900 border-2 border-purple-200 dark:border-purple-700 rounded-lg overflow-hidden">
                        @if($service->image_url)
                            <img src="{{ $service->image_url }}" alt="{{ $service->name }}" class="w-full h-32 object-cover">
                        @else
                            <div class="w-full h-32 bg-gradient-to-r from-purple-400 to-blue-400 flex items-center justify-center">
                                <span class="text-3xl">🎮</span>
                            </div>
                        @endif

                        <div class="p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-bold text-gray-900 dark:text-white">{{ $service->name }}</h3>
                                <span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded-full">⭐ Nổi bật</span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ Str::limit($service->description, 80) }}</p>
                            <div class="flex items-center justify-between">
                                <div class="text-lg font-bold text-green-600">{{ $service->formatted_price }}</div>
                                <button wire:click="openOrderModal({{ $service->id }})"
                                        class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                                    🛒 Đặt hàng
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Filters -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">🔍 Tìm kiếm</label>
                <input wire:model.live="search" type="text" placeholder="Tên dịch vụ..."
                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">📂 Danh mục</label>
                <select wire:model.live="categoryFilter" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Tất cả danh mục</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">💰 Mức giá</label>
                <select wire:model.live="priceFilter" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Tất cả mức giá</option>
                    <option value="low">≤ $10</option>
                    <option value="medium">$10 - $50</option>
                    <option value="high">> $50</option>
                </select>
            </div>
            <div class="flex items-end">
                <button wire:click="$set('search', '')"
                        class="w-full bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded-lg text-sm">
                    🔄 Reset
                </button>
            </div>
        </div>
    </div>

    <!-- Services Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-6">
        @forelse($services as $service)
            <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                @if($service->image_url)
                    <img src="{{ $service->image_url }}" alt="{{ $service->name }}" class="w-full h-40 object-cover">
                @else
                    <div class="w-full h-40 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                        <span class="text-4xl">🎮</span>
                    </div>
                @endif

                <div class="p-4">
                    <div class="flex items-start justify-between mb-2">
                        <h3 class="font-bold text-gray-900 dark:text-white text-sm">{{ $service->name }}</h3>
                        <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">{{ $service->category }}</span>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ Str::limit($service->description, 80) }}</p>

                    @if($service->features)
                        <div class="mb-3">
                            <div class="text-xs text-gray-500 mb-1">✨ Tính năng:</div>
                            <div class="text-xs text-gray-700 dark:text-gray-300">{{ Str::limit($service->features_list, 60) }}</div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between mb-3">
                        <div class="text-lg font-bold text-green-600">{{ $service->formatted_price }}</div>
                        <div class="text-xs text-gray-500">⏰ {{ $service->delivery_time }}</div>
                    </div>

                    <button wire:click="openOrderModal({{ $service->id }})"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                        🛒 Đặt hàng ngay
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12">
                <div class="text-6xl mb-4">🎮</div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Không tìm thấy dịch vụ nào</h3>
                <p class="text-gray-600 dark:text-gray-400">Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($services->hasPages())
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            {{ $services->links() }}
        </div>
    @endif

    <!-- Order Modal -->
    @if($showOrderModal && $selectedService)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">🛒 Đặt hàng dịch vụ</h3>

                <!-- Service Info -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-4">
                        @if($selectedService->image_url)
                            <img src="{{ $selectedService->image_url }}" alt="{{ $selectedService->name }}" class="w-20 h-20 object-cover rounded-lg">
                        @else
                            <div class="w-20 h-20 bg-gray-200 dark:bg-gray-600 rounded-lg flex items-center justify-center">
                                <span class="text-2xl">🎮</span>
                            </div>
                        @endif

                        <div class="flex-1">
                            <h4 class="font-bold text-gray-900 dark:text-white">{{ $selectedService->name }}</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ $selectedService->description }}</p>
                            <div class="flex items-center gap-4">
                                <span class="text-lg font-bold text-green-600">{{ $selectedService->formatted_price }}</span>
                                <span class="text-sm text-gray-500">⏰ {{ $selectedService->delivery_time }}</span>
                            </div>
                        </div>
                    </div>

                    @if($selectedService->detailed_description)
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <h5 class="font-medium text-gray-900 dark:text-white mb-2">📋 Mô tả chi tiết:</h5>
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $selectedService->detailed_description }}</p>
                        </div>
                    @endif

                    @if($selectedService->features)
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <h5 class="font-medium text-gray-900 dark:text-white mb-2">✨ Tính năng:</h5>
                            <ul class="text-sm text-gray-600 dark:text-gray-400 list-disc list-inside">
                                @foreach($selectedService->features as $feature)
                                    <li>{{ $feature }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($selectedService->requirements)
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                            <h5 class="font-medium text-gray-900 dark:text-white mb-2">📝 Yêu cầu cần cung cấp:</h5>
                            <ul class="text-sm text-gray-600 dark:text-gray-400 list-disc list-inside">
                                @foreach($selectedService->requirements as $requirement)
                                    <li>{{ $requirement }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <!-- Order Form -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Số lượng</label>
                        <input wire:model="orderQuantity" type="number" min="1" max="100"
                               class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                               placeholder="1">
                        @error('orderQuantity') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Thông tin yêu cầu *</label>
                        <textarea wire:model="customerRequirements" rows="4" maxlength="1000"
                                  class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                  placeholder="Vui lòng cung cấp thông tin chi tiết theo yêu cầu của dịch vụ..."></textarea>
                        @error('customerRequirements') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Total Calculation -->
                    <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-900 dark:text-white">Tổng thanh toán:</span>
                            <span class="text-xl font-bold text-green-600">
                                ${{ number_format($selectedService->price * ($orderQuantity ?: 1), 2) }}
                            </span>
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ $selectedService->formatted_price }} × {{ $orderQuantity ?: 1 }}
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button wire:click="placeOrder"
                            wire:loading.attr="disabled"
                            wire:loading.class="opacity-50 cursor-not-allowed"
                            class="flex-1 bg-green-600 hover:bg-green-700 disabled:hover:bg-green-600 text-white font-bold py-3 px-4 rounded-lg transition-all">
                        <span wire:loading.remove wire:target="placeOrder">✅ Xác nhận đặt hàng</span>
                        <span wire:loading wire:target="placeOrder" class="flex items-center justify-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Đang xử lý...
                        </span>
                    </button>
                    <button wire:click="closeOrderModal"
                            wire:loading.attr="disabled"
                            wire:target="placeOrder"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 disabled:opacity-50 text-white font-bold py-3 px-4 rounded-lg">
                        ❌ Hủy
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
