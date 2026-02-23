<?php $__env->startSection('title', 'Магазины'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Магазины</h1>
    <a href="<?php echo e(route('stores.create')); ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<table class="table table-hover">
    <thead>
        <tr><th>Название</th><th>Адрес</th><th>Телефон</th><th>Активен</th><th></th></tr>
    </thead>
    <tbody>
        <?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><?php echo e($s->name); ?></td>
            <td><?php echo e($s->address); ?></td>
            <td><?php echo e($s->phone); ?></td>
            <td><?php if($s->is_active): ?><span class="badge bg-success">Да</span><?php else: ?><span class="badge bg-secondary">Нет</span><?php endif; ?></td>
            <td>
                <a href="<?php echo e(route('stores.edit', $s)); ?>" class="btn btn-sm btn-outline-secondary">Изменить</a>
                <form action="<?php echo e(route('stores.destroy', $s)); ?>" method="post" class="d-inline" onsubmit="return confirm('Удалить?')">
                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                </form>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table>
<?php echo e($stores->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/stores/index.blade.php ENDPATH**/ ?>