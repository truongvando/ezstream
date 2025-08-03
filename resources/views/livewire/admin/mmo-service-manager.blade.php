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
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm">
                            ✏️ Sửa
                        </button>
                        <button wire:click="toggleActive({{ $service->id }})"
                                class="bg-{{ $service->is_active ? 'red' : 'green' }}-600 hover:bg-{{ $service->is_active ? 'red' : 'green' }}-700 text-white px-3 py-1 rounded text-sm">
                            {{ $service->is_active ? '❌' : '✅' }}
                        </button>
                        <button wire:click="toggleFeatured({{ $service->id }})"
                                class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded text-sm">
                            {{ $service->is_featured ? '⭐' : '☆' }}
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
