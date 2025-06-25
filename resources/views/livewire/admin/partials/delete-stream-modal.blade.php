<!-- Delete Confirmation Modal -->
<x-modal name="delete-stream-modal" :show="$showDeleteModal" :closeable="true">
    <div class="p-6">
        <div class="flex items-center mb-4">
            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
        </div>
        <div class="text-center">
            <h3 class="text-lg font-medium text-gray-900 mb-2">Xóa Stream</h3>
            <p class="text-sm text-gray-500 mb-6">Bạn có chắc chắn muốn xóa stream này? Hành động này không thể hoàn tác.</p>
            <div class="flex justify-center space-x-3">
                <x-secondary-button x-on:click="$dispatch('close-modal', { name: 'delete-stream-modal' })">Hủy</x-secondary-button>
                <x-danger-button wire:click="delete">Delete</x-danger-button>
            </div>
        </div>
    </div>
</x-modal> 