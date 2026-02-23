<?php $__env->startSection('title', $category->name); ?>

<?php $__env->startSection('content'); ?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo e(route('kb.index')); ?>">База знаний</a></li>
        <li class="breadcrumb-item active"><?php echo e($category->name); ?></li>
    </ol>
</nav>
<h1 class="h4 mb-4"><?php echo e($category->name); ?></h1>
<?php if($category->description): ?><p class="text-muted"><?php echo e($category->description); ?></p><?php endif; ?>
<ul class="list-group list-group-flush">
    <?php $__empty_1 = true; $__currentLoopData = $articles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $article): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <li class="list-group-item d-flex justify-content-between align-items-center">
        <a href="<?php echo e(route('kb.show', [$category->slug, $article->slug])); ?>"><?php echo e($article->title); ?></a>
    </li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <li class="list-group-item text-muted">В этой категории пока нет статей.</li>
    <?php endif; ?>
</ul>
<a href="<?php echo e(route('kb.index')); ?>" class="btn btn-secondary mt-3">← К списку категорий</a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/knowledge-base/category.blade.php ENDPATH**/ ?>