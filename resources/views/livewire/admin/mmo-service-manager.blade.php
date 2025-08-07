<div class="max-w-7xl mx-auto p-6">
    <!-- Header -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">🎮 Quản lý dịch vụ MMO</h2>
                <p class="text-gray-600 dark:text-gray-400">Thêm, sửa, xóa các dịch vụ MMO và quản lý đơn hàng</p>
            </div>
            <button wire:click="openServiceModal"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg font-medium">
                ➕ Thêm dịch vụ mới
            </button>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Tổng dịch vụ</div>
            <div class="text-2xl font-bold text-blue-600">{{ number_format($stats['total_services']) }}</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Đang hoạt động</div>
            <div class="text-2xl font-bold text-green-600">{{ number_format($stats['active_services']) }}</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Nổi bật</div>
            <div class="text-2xl font-bold text-purple-600">{{ number_format($stats['featured_services']) }}</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Đơn chờ</div>
            <div class="text-2xl font-bold text-yellow-600">{{ number_format($stats['pending_orders']) }}</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Đang xử lý</div>
            <div class="text-2xl font-bold text-orange-600">{{ number_format($stats['processing_orders']) }}</div>
        </div>
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            <div class="text-sm text-gray-600 dark:text-gray-400">Doanh thu</div>
            <div class="text-2xl font-bold text-red-600">${{ number_format($stats['total_revenue'], 2) }}</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tìm kiếm</label>
                <input wire:model.live="search" type="text" placeholder="Tên dịch vụ, mô tả..."
                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Danh mục</label>
                <select wire:model.live="categoryFilter" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Tất cả</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Trạng thái</label>
                <select wire:model.live="statusFilter" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-2 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                    <option value="">Tất cả</option>
                    <option value="active">Đang hoạt động</option>
                    <option value="inactive">Tạm tắt</option>
                    <option value="featured">Nổi bật</option>
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
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        @forelse($services as $service)
            <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg overflow-hidden">
                @if($service->image_url)
                    <img src="{{ $service->image_url }}" alt="{{ $service->name }}" class="w-full h-48 object-cover">
                @else
                    <div class="w-full h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                        <span class="text-4xl">🎮</span>
                    </div>
                @endif

                <div class="p-4">
                    <div class="flex items-start justify-between mb-2">
                        <h3 class="font-bold text-gray-900 dark:text-white">{{ $service->name }}</h3>
                        <div class="flex gap-1">
                            @if($service->is_featured)
                                <span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded-full">⭐ Nổi bật</span>
                            @endif
                            <span class="px-2 py-1 text-xs rounded-full {{ $service->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $service->is_active ? '✅ Hoạt động' : '❌ Tắt' }}
                            </span>
                        </div>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">{{ Str::limit($service->description, 100) }}</p>

                    <div class="flex items-center justify-between mb-3">
                        <div class="text-lg font-bold text-green-600">{{ $service->formatted_price }}</div>
                        <div class="text-sm text-gray-500">{{ $service->delivery_time }}</div>
                    </div>

                    @if($service->features)
                        <div class="mb-3">
                            <div class="text-xs text-gray-500 mb-1">Tính năng:</div>
                            <div class="text-sm text-gray-700 dark:text-gray-300">{{ Str::limit($service->features_list, 80) }}</div>
                        </div>
                    @endif

                    <div class="flex gap-2">
                        <button wire:click="openServiceModal({{ $service->id }})"
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm flex items-center justify-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Sửa
                        </button>
                        <button wire:click="toggleActive({{ $service->id }})"
                                class="bg-{{ $service->is_active ? 'red' : 'green' }}-600 hover:bg-{{ $service->is_active ? 'red' : 'green' }}-700 text-white px-3 py-1 rounded text-sm flex items-center justify-center">
                            @if($service->is_active)
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                </svg>
                            @else
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            @endif
                        </button>
                        <button wire:click="toggleFeatured({{ $service->id }})"
                                class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded text-sm flex items-center justify-center">
                            @if($service->is_featured)
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                            @else
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                                </svg>
                            @endif
                        </button>
                        <button wire:click="deleteService({{ $service->id }})"
                                onclick="return confirm('Bạn có chắc muốn xóa dịch vụ này?')"
                                class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">
                            🗑️
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12">
                <div class="text-6xl mb-4">🎮</div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Chưa có dịch vụ MMO nào</h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Thêm dịch vụ MMO đầu tiên để bắt đầu</p>
                <button wire:click="openServiceModal"
                        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                    ➕ Thêm dịch vụ mới
                </button>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($services->hasPages())
        <div class="bg-white border border-gray-200 dark:bg-gray-800 dark:border-gray-700 rounded-lg p-4">
            {{ $services->links() }}
        </div>
    @endif

    <!-- Service Modal -->
    @if($showServiceModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">
                    {{ $editingServiceId ? '✏️ Sửa dịch vụ MMO' : '➕ Thêm dịch vụ MMO mới' }}
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tên dịch vụ *</label>
                            <input wire:model="serviceName" type="text" maxlength="255"
                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                   placeholder="Ví dụ: Tăng follow Instagram">
                            @error('serviceName') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mô tả ngắn *</label>
                            <textarea wire:model="serviceDescription" rows="3" maxlength="1000"
                                      class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                      placeholder="Mô tả ngắn gọn về dịch vụ..."></textarea>
                            @error('serviceDescription') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mô tả chi tiết</label>
                            <textarea wire:model="serviceDetailedDescription" rows="4"
                                      class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                      placeholder="Mô tả chi tiết về dịch vụ, quy trình thực hiện..."></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Giá ($) *</label>
                                <input wire:model="servicePrice" type="number" step="0.01" min="0.01" max="10000"
                                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                       placeholder="0.00">
                                @error('servicePrice') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Thời gian giao hàng *</label>
                                <input wire:model="serviceDeliveryTime" type="text" maxlength="100"
                                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                       placeholder="1-24 hours">
                                @error('serviceDeliveryTime') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Danh mục *</label>
                            <input wire:model="serviceCategory" type="text" maxlength="100"
                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                   placeholder="MMO, Social Media, Gaming...">
                            @error('serviceCategory') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">URL hình ảnh</label>
                            <input wire:model="serviceImageUrl" type="url" maxlength="500"
                                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                   placeholder="https://example.com/image.jpg">
                            @error('serviceImageUrl') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tính năng (phân cách bằng dấu phẩy)</label>
                            <textarea wire:model="serviceFeatures" rows="3"
                                      class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                      placeholder="Tính năng 1, Tính năng 2, Tính năng 3..."></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Yêu cầu từ khách hàng (phân cách bằng dấu phẩy)</label>
                            <textarea wire:model="serviceRequirements" rows="3"
                                      class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                      placeholder="Username, Link profile, Số lượng..."></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ghi chú admin</label>
                            <textarea wire:model="serviceNotes" rows="3"
                                      class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                      placeholder="Ghi chú nội bộ cho admin..."></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Thứ tự sắp xếp</label>
                                <input wire:model="serviceSortOrder" type="number" min="0" max="999"
                                       class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                                       placeholder="0">
                            </div>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input wire:model="serviceIsActive" type="checkbox" class="mr-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">✅ Kích hoạt dịch vụ</span>
                                </label>
                                <label class="flex items-center">
                                    <input wire:model="serviceIsFeatured" type="checkbox" class="mr-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">⭐ Dịch vụ nổi bật</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 mt-6">
                    <button wire:click="saveService"
                            class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        ✅ {{ $editingServiceId ? 'Cập nhật' : 'Tạo mới' }}
                    </button>
                    <button wire:click="closeServiceModal"
                            class="flex-1 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                        ❌ Hủy
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
