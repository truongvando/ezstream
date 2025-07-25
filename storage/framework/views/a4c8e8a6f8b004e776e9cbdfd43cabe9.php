<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['status']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['status']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    $baseClasses = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize';
    
    $colorClasses = match ($status) {
        'STREAMING' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        'STARTING' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        'STOPPED', 'INACTIVE' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        'ERROR' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        'STOPPING' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        default => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    };
?>

<span class="<?php echo e($baseClasses); ?> <?php echo e($colorClasses); ?>">
    <?php echo e($status); ?>

</span> <?php /**PATH D:\laragon\www\ezstream\resources\views/components/stream-status-badge.blade.php ENDPATH**/ ?>