<?php $__env->startSection('title', 'Товары'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4">Товары</h1>
<form method="get" class="mb-3 row g-2">
    <div class="col-auto"><input type="text" name="search" class="form-control form-control-sm" placeholder="Название, штрихкод" value="<?php echo e(request('search')); ?>"></div>
    <div class="col-auto"><select name="status_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Все статусы</option><?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s->id); ?>" <?php echo e(request('status_id') == $s->id ? 'selected' : ''); ?>><?php echo e($s->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-secondary">Найти</button></div>
</form>
<table class="table table-hover">
    <thead><tr><th>Штрихкод</th><th>Название</th><th>Магазин</th><th>Статус</th><th>Цена</th><th></th></tr></thead>
    <tbody>
        <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><code><?php echo e($i->barcode); ?></code></td>
            <td><a href="<?php echo e(route('items.show', $i)); ?>"><?php echo e($i->name); ?></a></td>
            <td><?php echo e($i->store->name); ?></td>
            <td><?php if($i->status): ?><span class="badge" <?php if($i->status->color): ?> style="background-color:<?php echo e($i->status->color); ?>" <?php endif; ?>><?php echo e($i->status->name); ?></span><?php else: ?>—<?php endif; ?></td>
            <td><?php echo e($i->current_price ? number_format($i->current_price, 0, '', ' ') . ' ₽' : '—'); ?></td>
            <td>
                <a href="<?php echo e(route('items.show', $i)); ?>" class="btn btn-sm btn-outline-primary">Карточка</a>
                <?php if(auth()->user()->canManageStorage()): ?>
                <a href="<?php echo e(route('items.edit', $i)); ?>" class="btn btn-sm btn-outline-secondary">Изменить</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table>
<?php echo e($items->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/items/index.blade.php ENDPATH**/ ?>