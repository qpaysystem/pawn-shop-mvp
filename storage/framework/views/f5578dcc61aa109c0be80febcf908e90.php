<?php $__env->startSection('title', 'База знаний'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">База знаний</h1>
    <?php if(auth()->guard()->check()): ?>
    <?php if(auth()->user()->hasFullStoreAccess() || auth()->user()->isSuperAdmin()): ?>
    <a href="<?php echo e(route('kb.categories.index')); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Управление</a>
    <?php endif; ?>
    <?php endif; ?>
</div>
<p class="text-muted">Обучение нового персонала и регламентные документы.</p>
<div class="row g-3">
    <?php $__empty_1 = true; $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><a href="<?php echo e(route('kb.category', $cat->slug)); ?>" class="text-decoration-none"><?php echo e($cat->name); ?></a></h5>
                <?php if($cat->description): ?>
                <p class="card-text small text-muted"><?php echo e(\Illuminate\Support\Str::limit($cat->description, 120)); ?></p>
                <?php endif; ?>
                <p class="mb-0 small">Статей: <?php echo e($cat->articles_count); ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="col-12">
        <div class="alert alert-info">Пока нет категорий. <?php if(auth()->guard()->check()): ?> <?php if(auth()->user()->hasFullStoreAccess() || auth()->user()->isSuperAdmin()): ?><a href="<?php echo e(route('kb.categories.create')); ?>">Создать категорию</a><?php endif; ?> <?php endif; ?></div>
    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/knowledge-base/index.blade.php ENDPATH**/ ?>