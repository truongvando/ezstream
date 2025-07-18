<!-- Stream Create/Edit Modal -->
<div x-show="$wire.showCreateModal || $wire.showEditModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="$wire.closeModal()"></div>
        
        <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl w-full max-h-[90vh] flex flex-col">
            <!-- Header -->
            <div class="bg-white dark:bg-gray-800 px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                        <?php echo e($showCreateModal ? 'Tạo Stream Mới' : 'Chỉnh Sửa Stream'); ?>

                    </h3>
                    <button @click="$wire.closeModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>

            <!-- Form Body (Scrollable) -->
            <form wire:submit.prevent="<?php echo e($showCreateModal ? 'store' : 'update'); ?>" class="flex-grow overflow-y-auto">
                <div class="px-6 py-6 space-y-6">
                    <!-- Nội dung form ở đây -->
                    <?php echo $__env->make('livewire.shared.stream-form-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </div>
            </form>

            <!-- Footer -->
            <div class="bg-gray-50 dark:bg-gray-900 px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex-shrink-0 flex justify-end space-x-3">
                <button type="button" wire:click="closeModal" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">Hủy</button>
                <button type="button" wire:click="<?php echo e($showCreateModal ? 'store' : 'update'); ?>" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"><?php echo e($showCreateModal ? 'Tạo Stream' : 'Cập Nhật'); ?></button>
            </div>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/shared/stream-modal.blade.php ENDPATH**/ ?>