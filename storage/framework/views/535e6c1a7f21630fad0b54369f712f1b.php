<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label', 'percentage', 'color' => 'gray']));

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

foreach (array_filter((['label', 'percentage', 'color' => 'gray']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>

<?php
    $bgColorClass = "bg-{$color}-500";
?>

<div>
    <div class="flex justify-between mb-1">
        <span class="text-xs font-medium text-gray-700 dark:text-gray-300"><?php echo e($label); ?></span>
        <span class="text-xs font-medium text-gray-700 dark:text-gray-300"><?php echo e(number_format($percentage, 2)); ?>%</span>
    </div>
    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
        <div class="<?php echo e($bgColorClass); ?> h-2 rounded-full" style="width:<?php echo e($percentage); ?>%"></div>
    </div>
</div> <?php /**PATH D:\laragon\www\ezstream\resources\views/components/vps-stat-bar.blade.php ENDPATH**/ ?>