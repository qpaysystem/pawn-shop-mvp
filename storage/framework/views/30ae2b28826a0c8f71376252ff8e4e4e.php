<?php $__env->startSection('title', 'Договоры комиссии'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4">Договоры комиссии</h1>
<form method="get" class="mb-3">
    <select name="sold" class="form-select form-select-sm w-auto d-inline-block" onchange="this.form.submit()">
        <option value="">Все</option>
        <option value="0" <?php echo e(request('sold') === '0' ? 'selected' : ''); ?>>Не проданы</option>
        <option value="1" <?php echo e(request('sold') === '1' ? 'selected' : ''); ?>>Проданные</option>
    </select>
</form>
<table class="table table-hover">
    <thead><tr><th>№ договора</th><th>Клиент</th><th>Товар</th><th>Цена продажи</th><th>Продано</th><th></th></tr></thead>
    <tbody>
        <?php $__currentLoopData = $contracts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><?php echo e($c->contract_number); ?></td>
            <td><a href="<?php echo e(route('clients.show', $c->client)); ?>"><?php echo e($c->client->full_name); ?></a></td>
            <td><a href="<?php echo e(route('items.show', $c->item)); ?>"><?php echo e($c->item->name); ?></a></td>
            <td><?php echo e($c->seller_price ? number_format($c->seller_price, 0, '', ' ') . ' ₽' : '—'); ?></td>
            <td><?php if($c->is_sold): ?><span class="badge bg-success">Да</span><?php else: ?><span class="badge bg-warning">Нет</span><?php endif; ?></td>
            <td><a href="<?php echo e(route('commission-contracts.show', $c)); ?>" class="btn btn-sm btn-outline-primary">Подробнее</a></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table>
<?php echo e($contracts->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/commission-contracts/index.blade.php ENDPATH**/ ?>