<?php $__env->startSection('title', 'Договоры залога'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4">Договоры залога</h1>
<form method="get" class="mb-3">
    <select name="redeemed" class="form-select form-select-sm w-auto d-inline-block" onchange="this.form.submit()">
        <option value="">Все</option>
        <option value="0" <?php echo e(request('redeemed') === '0' ? 'selected' : ''); ?>>Активные</option>
        <option value="1" <?php echo e(request('redeemed') === '1' ? 'selected' : ''); ?>>Выкупленные</option>
    </select>
</form>
<table class="table table-hover">
    <thead><tr><th>№ договора</th><th>Клиент</th><th>Товар</th><th>Сумма займа</th><th>Выкуп</th><th>Срок</th><th></th></tr></thead>
    <tbody>
        <?php $__currentLoopData = $contracts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><?php echo e($c->contract_number); ?></td>
            <td><a href="<?php echo e(route('clients.show', $c->client)); ?>"><?php echo e($c->client->full_name); ?></a></td>
            <td><a href="<?php echo e(route('items.show', $c->item)); ?>"><?php echo e($c->item->name); ?></a></td>
            <td><?php echo e(number_format($c->loan_amount, 0, '', ' ')); ?> ₽</td>
            <td><?php echo e(number_format($c->redemption_amount, 0, '', ' ')); ?> ₽</td>
            <td><?php if($c->is_redeemed): ?><span class="badge bg-success">Да</span><?php else: ?><span class="badge bg-warning">Нет</span><?php endif; ?></td>
            <td><?php echo e(\Carbon\Carbon::parse($c->expiry_date)->format('d.m.Y')); ?></td>
            <td><a href="<?php echo e(route('pawn-contracts.show', $c)); ?>" class="btn btn-sm btn-outline-primary">Подробнее</a></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table>
<?php echo e($contracts->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/pawn-contracts/index.blade.php ENDPATH**/ ?>