<div wire:poll.5s="refreshStreams">
    @if(isset($isAdmin) && $isAdmin)
        <!-- Admin layout without extra wrapper -->
        @include('livewire.shared.stream-cards')
        @include('livewire.shared.stream-modal')
        @include('livewire.shared.quick-stream-modal')
        
        <!-- Delete Modal -->
        <div x-show="$wire.showDeleteModal" x-cloak
             class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                     @click="$wire.showDeleteModal = false"></div>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-6 py-4">
                        <div class="flex items-center mb-4">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="text-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Xóa Stream</h3>
                            @if($deletingStream)
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                                    Bạn có chắc chắn muốn xóa stream "<strong>{{ $deletingStream->title }}</strong>"? Hành động này không thể hoàn tác.
                                </p>
                            @endif
                            <div class="flex justify-center space-x-3">
                                <button @click="$wire.showDeleteModal = false"
                                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    Hủy
                                </button>
                                <button wire:click="delete"
                                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                                    Xóa Stream
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- User layout with wrapper -->
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                @include('livewire.shared.stream-cards')
                @include('livewire.shared.stream-modal')
                @include('livewire.shared.quick-stream-modal')
                
                <!-- Delete Modal -->
                <div x-show="$wire.showDeleteModal" x-cloak
                     class="fixed inset-0 z-50 overflow-y-auto">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                             @click="$wire.showDeleteModal = false"></div>

                        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                            <div class="bg-white dark:bg-gray-800 px-6 py-4">
                                <div class="text-center">
                                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900">
                                        <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"/>
                                        </svg>
                                    </div>
                                    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">Xóa Stream</h3>
                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        Bạn có chắc chắn muốn xóa stream <strong class="font-medium text-gray-900 dark:text-white">{{ $deletingStream->title ?? '' }}</strong>? Hành động này không thể hoàn tác.
                                    </p>
                                    <div class="mt-6 flex justify-center space-x-3">
                                        <button @click="$wire.showDeleteModal = false"
                                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            Hủy
                                        </button>
                                        <button wire:click="delete"
                                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700">
                                            Xóa Stream
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
