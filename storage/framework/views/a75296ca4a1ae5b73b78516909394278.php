<div wire:poll.3s="refreshStreams">
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Debug info (remove in production) -->
            <div class="mb-2 text-xs text-gray-500">
                Last refresh: <?php echo e(now()->format('H:i:s')); ?> | Polling every 3s
            </div>

            <?php echo $__env->make('livewire.shared.stream-cards', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('livewire.shared.stream-form-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('livewire.shared.quick-stream-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
    </div>
</div>
<?php /**PATH D:\laragon\www\ezstream\resources\views/livewire/user-stream-manager.blade.php ENDPATH**/ ?>