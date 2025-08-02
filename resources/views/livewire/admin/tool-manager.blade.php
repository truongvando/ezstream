<div class="p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">🛠️ Quản lý Tools</h1>
        <p class="text-gray-600 dark:text-gray-400">Quản lý tất cả Python tools trong cửa hàng</p>


    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 7.172V5L8 4z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Tổng Tools</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_tools'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Tools Hoạt động</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['active_tools'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-yellow-100 dark:bg-yellow-900 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Tools Nổi bật</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['featured_tools'] }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center">
                <div class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Tổng Đơn hàng</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_orders'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Actions -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="relative">
                        <input type="text" wire:model.live="search" placeholder="Tìm kiếm tools..." 
                               class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>

                    <select wire:model.live="statusFilter" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="active">Hoạt động</option>
                        <option value="inactive">Không hoạt động</option>
                        <option value="featured">Nổi bật</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <!-- New approach: Direct link to create page -->
                    <a href="{{ route('admin.tools.create') }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Thêm Tool Mới
                    </a>

                    <!-- Old modal approach (for comparison) -->
                    <button wire:click="showCreateModal"
                            class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors duration-200 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Modal (Debug)
                    </button>
                </div>
            </div>
        </div>

        <!-- Tools Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tool</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Giá</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Đơn hàng</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Trạng thái</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($tools as $tool)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <img class="h-12 w-12 rounded-lg object-cover" src="{{ $tool->image }}" alt="{{ $tool->name }}">
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $tool->name }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ Str::limit($tool->short_description, 50) }}</div>
                                        @if($tool->is_featured)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                ⭐ Nổi bật
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    @if($tool->sale_price)
                                        <span class="line-through text-gray-500">${{ number_format($tool->price, 2) }}</span>
                                        <span class="text-red-600 font-medium">${{ number_format($tool->sale_price, 2) }}</span>
                                    @else
                                        <span class="font-medium">${{ number_format($tool->price, 2) }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                {{ $tool->tool_orders_count }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($tool->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Hoạt động
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        Tạm dừng
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <button wire:click="showEditModal({{ $tool->id }})" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                        Sửa
                                    </button>
                                    <button wire:click="toggleStatus({{ $tool->id }})" class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300">
                                        {{ $tool->is_active ? 'Tắt' : 'Bật' }}
                                    </button>
                                    <button wire:click="deleteTool({{ $tool->id }})" 
                                            onclick="return confirm('Bạn có chắc muốn xóa tool này?')"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                        Xóa
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                Không có tools nào
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $tools->links() }}
        </div>
    </div>



    <!-- SIMPLE WORKING MODAL -->
    @if($showCreateModal)
        <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999999; display: flex; align-items: center; justify-content: center;"
             wire:click="closeModals">
            <div style="background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;"
                 wire:click.stop
                 class="dark:bg-gray-800">

                <!-- Header -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 15px;">
                    <h2 style="font-size: 24px; font-weight: bold; color: #111827; margin: 0;" class="dark:text-white">
                        {{ $showCreateModal ? '🛠️ Thêm Tool Mới' : '✏️ Sửa Tool' }}
                    </h2>
                    <button wire:click="closeModals"
                            style="background: none; border: none; color: #6b7280; cursor: pointer; padding: 5px;">
                        ✕
                    </button>
                </div>

                <!-- Simple Form -->
                <form wire:submit="{{ $showCreateModal ? 'createTool' : 'updateTool' }}">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;" class="dark:text-gray-300">Tên Tool:</label>
                        <input type="text" wire:model="name"
                               style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;"
                               class="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                               required>
                        @error('name') <span style="color: red; font-size: 12px;">{{ $message }}</span> @enderror
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;" class="dark:text-gray-300">Slug:</label>
                        <input type="text" wire:model="slug"
                               style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;"
                               class="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                               required>
                        @error('slug') <span style="color: red; font-size: 12px;">{{ $message }}</span> @enderror
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;" class="dark:text-gray-300">Giá (USD):</label>
                        <input type="number" step="0.01" wire:model="price"
                               style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;"
                               class="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                               required>
                        @error('price') <span style="color: red; font-size: 12px;">{{ $message }}</span> @enderror
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;" class="dark:text-gray-300">Mô tả ngắn:</label>
                        <textarea wire:model="short_description" rows="2"
                                  style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; resize: vertical;"
                                  class="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                  required></textarea>
                        @error('short_description') <span style="color: red; font-size: 12px;">{{ $message }}</span> @enderror
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;" class="dark:text-gray-300">Mô tả chi tiết:</label>
                        <textarea wire:model="description" rows="3"
                                  style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; resize: vertical;"
                                  class="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                  required></textarea>
                        @error('description') <span style="color: red; font-size: 12px;">{{ $message }}</span> @enderror
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;" class="dark:text-gray-300">URL Hình ảnh:</label>
                        <input type="url" wire:model="image"
                               style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;"
                               class="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                               required>
                        @error('image') <span style="color: red; font-size: 12px;">{{ $message }}</span> @enderror
                    </div>

                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 500;" class="dark:text-gray-300">URL Download:</label>
                        <input type="url" wire:model="download_url"
                               style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px;"
                               class="dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                               required>
                        @error('download_url') <span style="color: red; font-size: 12px;">{{ $message }}</span> @enderror
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" wire:model="is_active" style="margin-right: 8px;">
                            <span class="dark:text-gray-300">Kích hoạt tool</span>
                        </label>
                    </div>

                    <!-- Actions -->
                    <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid #e5e7eb; padding-top: 15px;">
                        <button type="button" wire:click="closeModals"
                                style="padding: 10px 20px; background: #6b7280; color: white; border: none; border-radius: 6px; cursor: pointer;">
                            Hủy
                        </button>
                        <button type="submit"
                                style="padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 6px; cursor: pointer;">
                            {{ $showCreateModal ? 'Tạo Tool' : 'Cập nhật' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif



    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('error') }}
        </div>
    @endif
</div>
