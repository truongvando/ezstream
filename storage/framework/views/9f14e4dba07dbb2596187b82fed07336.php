<?php extract((new \Illuminate\Support\Collection($attributes->getAttributes()))->mapWithKeys(function ($value, $key) { return [Illuminate\Support\Str::camel(str_replace([':', '.'], ' ', $key)) => $value]; })->all(), EXTR_SKIP); ?>
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['status','class']));

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

foreach (array_filter((['status','class']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars); ?>
<?php if (isset($component)) { $__componentOriginal8696315fddaaf073a3eb86bb539a6d7b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8696315fddaaf073a3eb86bb539a6d7b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.stream-status-icon','data' => ['status' => $status,'class' => $class]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('stream-status-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($status),'class' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($class)]); ?>

<?php echo e($slot ?? ""); ?>

 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8696315fddaaf073a3eb86bb539a6d7b)): ?>
<?php $attributes = $__attributesOriginal8696315fddaaf073a3eb86bb539a6d7b; ?>
<?php unset($__attributesOriginal8696315fddaaf073a3eb86bb539a6d7b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8696315fddaaf073a3eb86bb539a6d7b)): ?>
<?php $component = $__componentOriginal8696315fddaaf073a3eb86bb539a6d7b; ?>
<?php unset($__componentOriginal8696315fddaaf073a3eb86bb539a6d7b); ?>
<?php endif; ?><?php /**PATH D:\laragon\www\ezstream\storage\framework\views/5fcf577f886ede2dfe2743ab07bd9cff.blade.php ENDPATH**/ ?>