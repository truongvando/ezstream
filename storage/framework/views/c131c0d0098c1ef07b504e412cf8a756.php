<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('header', null, []); ?> 
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            <?php echo e($post->title); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="h-full bg-white dark:bg-gray-800">
        <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">
                    <?php if($post->featured_image): ?>
                        <img src="<?php echo e($post->featured_image); ?>" alt="<?php echo e($post->title); ?>" class="w-full h-auto rounded-lg mb-8">
                    <?php endif; ?>

                    <h1 class="text-3xl md:text-4xl font-bold mb-4"><?php echo e($post->title); ?></h1>
                    
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        <span>Đăng ngày <?php echo e($post->created_at->format('d/m/Y')); ?></span>
                    </div>

                    <div class="prose dark:prose-invert max-w-none">
                        <?php echo $post->body; ?>

                    </div>

                    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <a href="<?php echo e(route('blog.index')); ?>" class="text-blue-600 dark:text-blue-400 hover:underline">
                            &larr; <?php echo e(__('Back to Blog')); ?>

                        </a>
                    </div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH D:\laragon\www\ezstream\resources\views\blog\show.blade.php ENDPATH**/ ?>