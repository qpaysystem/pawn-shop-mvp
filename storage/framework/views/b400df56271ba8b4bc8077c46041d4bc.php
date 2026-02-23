<?php $__env->startSection('title', 'Категории товаров'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Категории товаров</h1>
    <a href="<?php echo e(route('item-categories.create')); ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<table class="table table-hover">
    <thead>
        <tr><th>Название</th><th>Родитель</th><th></th></tr>
    </thead>
    <tbody>
        <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><?php echo e($c->name); ?></td>
            <td><?php echo e($c->parent?->name ?? '—'); ?></td>
            <td>
                <a href="<?php echo e(route('item-categories.edit', $c)); ?>" class="btn btn-sm btn-outline-secondary">Изменить</a>
                <form action="<?php echo e(route('item-categories.destroy', $c)); ?>" method="post" class="d-inline" onsubmit="return confirm('Удалить?')">
                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                </form>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table>
<?php echo e($categories->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/item-categories/index.blade.php ENDPATH**/ ?>