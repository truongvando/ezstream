<div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6">
    <!-- Header -->
    <div class="flex items-center mb-6">
        <div class="bg-purple-100 dark:bg-purple-900 p-3 rounded-lg mr-4">
            <svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 7.172V5L8 4z"/>
            </svg>
        </div>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">🛠️ Cửa hàng Tool</h2>
            <p class="text-gray-600 dark:text-gray-400">Khám phá các công cụ chuyên nghiệp cho content creator</p>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-blue-100 dark:bg-blue-800 p-2 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 7.172V5L8 4z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ $stats['total_tools'] }}</div>
                    <div class="text-sm text-blue-700 dark:text-blue-300">Tổng số tools</div>
                </div>
            </div>
        </div>

        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-yellow-100 dark:bg-yellow-800 p-2 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-yellow-900 dark:text-yellow-100">{{ $stats['featured_tools'] }}</div>
                    <div class="text-sm text-yellow-700 dark:text-yellow-300">Tools nổi bật</div>
                </div>
            </div>
        </div>

        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
            <div class="flex items-center">
                <div class="bg-green-100 dark:bg-green-800 p-2 rounded-lg mr-3">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-900 dark:text-green-100">{{ $stats['on_sale_tools'] }}</div>
                    <div class="text-sm text-green-700 dark:text-green-300">Đang giảm giá</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="md:col-span-2">
            <input wire:model.live.debounce.300ms="search" type="text" 
                   class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white" 
                   placeholder="🔍 Tìm kiếm tool...">
        </div>
        
        <div>
            <select wire:model.live="priceRange" 
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                <option value="all">💰 Tất cả giá</option>
                <option value="under_200k">Dưới 200k</option>
                <option value="200k_500k">200k - 500k</option>
                <option value="over_500k">Trên 500k</option>
            </select>
        </div>

        <div>
            <select wire:model.live="sortBy" 
                    class="w-full border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                <option value="created_at">📅 Mới nhất</option>
                <option value="name">🔤 Tên A-Z</option>
                <option value="price">💵 Giá</option>
                <option value="sort_order">⭐ Nổi bật</option>
            </select>
        </div>
    </div>

    <!-- Filter Options -->
    <div class="flex flex-wrap gap-3 mb-6">
        <label class="flex items-center">
            <input wire:model.live="showFeaturedOnly" type="checkbox" 
                   class="rounded border-gray-300 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50">
            <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">⭐ Chỉ hiện tools nổi bật</span>
        </label>

        @if($search || $showFeaturedOnly || $priceRange !== 'all')
            <button wire:click="clearFilters" 
                    class="text-sm text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-200">
                🗑️ Xóa bộ lọc
            </button>
        @endif
    </div>

    <!-- Tools Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        @forelse($tools as $tool)
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-200 bg-white dark:bg-gray-700">
                <!-- Tool Image -->
                <div class="relative">
                    <img src="{{ $tool->image }}" alt="{{ $tool->name }}" 
                         class="w-full h-48 object-cover">
                    
                    @if($tool->is_featured)
                        <div class="absolute top-2 left-2">
                            <span class="bg-yellow-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                                ⭐ Nổi bật
                            </span>
                        </div>
                    @endif

                    @if($tool->is_on_sale)
                        <div class="absolute top-2 right-2">
                            <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                                🏷️ Sale
                            </span>
                        </div>
                    @endif
                </div>

                <!-- Tool Info -->
                <div class="p-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ $tool->name }}</h3>
                    <p class="text-gray-600 dark:text-gray-400 text-sm mb-3 line-clamp-2">{{ $tool->short_description }}</p>
                    
                    <!-- Features -->
                    @if($tool->features && count($tool->features) > 0)
                        <div class="mb-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach(array_slice($tool->features, 0, 3) as $feature)
                                    <span class="bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs px-2 py-1 rounded">
                                        {{ $feature }}
                                    </span>
                                @endforeach
                                @if(count($tool->features) > 3)
                                    <span class="text-gray-500 text-xs">+{{ count($tool->features) - 3 }} more</span>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Price & Action -->
                    <div class="flex justify-between items-center">
                        <div>
                            @if($tool->is_on_sale)
                                <div class="flex items-center space-x-2">
                                    <span class="line-through text-gray-400 text-sm">
                                        {{ number_format($tool->price) }}đ
                                    </span>
                                    <span class="text-red-600 dark:text-red-400 font-bold">
                                        {{ number_format($tool->final_price) }}đ
                                    </span>
                                </div>
                            @else
                                <span class="text-gray-900 dark:text-white font-bold">
                                    {{ number_format($tool->final_price) }}đ
                                </span>
                            @endif
                        </div>
                        
                        <a href="{{ route('tools.show', $tool->slug) }}" 
                           class="bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                            Chi tiết
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12">
                <div class="text-gray-400 dark:text-gray-500 text-6xl mb-4">🔍</div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Không tìm thấy tool nào</h3>
                <p class="text-gray-600 dark:text-gray-400">Thử thay đổi từ khóa tìm kiếm hoặc bộ lọc</p>
                @if($search || $showFeaturedOnly || $priceRange !== 'all')
                    <button wire:click="clearFilters" 
                            class="mt-4 text-purple-600 hover:text-purple-800 dark:text-purple-400 dark:hover:text-purple-200">
                        Xóa tất cả bộ lọc
                    </button>
                @endif
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($tools->hasPages())
        <div class="mt-6">
            {{ $tools->links() }}
        </div>
    @endif
</div>
