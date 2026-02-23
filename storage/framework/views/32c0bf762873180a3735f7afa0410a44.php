<?php $__env->startSection('title', 'Места хранения'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Места хранения</h1>
    <a href="<?php echo e(route('storage-locations.create')); ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<form method="get" class="mb-3 row g-2">
    <div class="col-auto"><select name="store_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Все магазины</option><?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s->id); ?>" <?php echo e(request('store_id') == $s->id ? 'selected' : ''); ?>><?php echo e($s->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
</form>
<table class="table table-hover">
    <thead><tr><th>Название</th><th>Магазин</th><th></th></tr></thead>
    <tbody>
        <?php $__currentLoopData = $locations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $loc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><?php echo e($loc->name); ?></td>
            <td><?php echo e($loc->store->name); ?></td>
            <td>
                <a href="<?php echo e(route('storage-locations.edit', $loc)); ?>" class="btn btn-sm btn-outline-secondary">Изменить</a>
                <form action="<?php echo e(route('storage-locations.destroy', $loc)); ?>" method="post" class="d-inline" onsubmit="return confirm('Удалить?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button></form>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table>
<?php echo e($locations->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/storage-locations/index.blade.php ENDPATH**/ ?>