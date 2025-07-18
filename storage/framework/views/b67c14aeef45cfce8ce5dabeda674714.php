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
            <?php echo e(__('Blog')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="h-full bg-white dark:bg-gray-800">
        <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php $__empty_1 = true; $__currentLoopData = $posts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $post): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <a href="<?php echo e($post->link); ?>" target="_blank" class="block bg-gray-100 dark:bg-gray-700 rounded-lg overflow-hidden hover:shadow-lg transition-shadow">
                                <?php if($post->featured_image): ?>
                                    <img src="<?php echo e(asset('storage/' . $post->featured_image)); ?>" alt="<?php echo e($post->title); ?>" class="w-full h-48 object-cover">
                                <?php else: ?>
                                     <div class="w-full h-48 bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="text-gray-500">No Image</span>
                                    </div>
                                <?php endif; ?>
                                <div class="p-4">
                                    <h3 class="font-bold text-lg mb-2 text-blue-600 dark:text-blue-400">
                                        <?php echo e($post->title); ?>

                                    </h3>
                                    <div class="flex justify-between items-center mt-4">
                                        <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($post->created_at->format('d/m/Y')); ?></span>
                                        <?php if($post->link): ?>
                                            <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full text-xs font-semibold">
                                                🔗 Link
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <div class="col-span-full text-center py-12">
                                <p class="text-gray-500 dark:text-gray-400">Chưa có bài viết nào.</p>
                                <a href="<?php echo e(route('admin.blog.create')); ?>" class="mt-4 inline-block px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                    Tạo bài viết đầu tiên
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-8">
                        <?php echo e($posts->links()); ?>

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
<?php /**PATH D:\laragon\www\ezstream\resources\views/blog/index.blade.php ENDPATH**/ ?>