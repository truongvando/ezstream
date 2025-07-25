<?php if(session()->has('success') || session()->has('error')): ?>
    <div 
        x-data="{ show: true }" 
        x-show="show" 
        x-init="setTimeout(() => show = false, 5000)"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-90"
        class="fixed top-5 right-5 z-50 px-4 py-3 rounded-lg shadow-lg text-white <?php echo e(session()->has('success') ? 'bg-green-500' : 'bg-red-500'); ?>"
        role="alert">
        <p class="font-bold"><?php echo e(session()->has('success') ? 'Success' : 'Error'); ?></p>
        <p><?php echo e(session('success') ?? session('error')); ?></p>
    </div>
<?php endif; ?> <?php /**PATH D:\laragon\www\ezstream\resources\views/components/flash-message.blade.php ENDPATH**/ ?>