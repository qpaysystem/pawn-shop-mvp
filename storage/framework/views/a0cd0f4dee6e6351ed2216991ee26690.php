<?php $__env->startSection('title', 'Профиль'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4">Профиль: <?php echo e($user->name); ?></h1>
<div class="card mb-4">
    <div class="card-body">
        <p class="mb-1"><strong>Роль:</strong> <?php echo e($user->role); ?></p>
        <?php if($user->store): ?><p class="mb-1"><strong>Магазин:</strong> <?php echo e($user->store->name); ?></p><?php endif; ?>
        <p class="mb-0"><strong>Email:</strong> <?php echo e($user->email); ?></p>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="profileTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pawn-tab" data-bs-toggle="tab" data-bs-target="#pawn" type="button" role="tab">Договоры займа</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="commission-tab" data-bs-toggle="tab" data-bs-target="#commission" type="button" role="tab">Договоры комиссии</button>
    </li>
</ul>
<div class="tab-content" id="profileTabsContent">
    <div class="tab-pane fade show active" id="pawn" role="tabpanel">
        <p class="text-muted small">Договоры залога, где вы указаны приёмщиком (оценщиком).</p>
        <?php if($pawnContracts->isEmpty()): ?>
            <p class="text-muted">Нет договоров.</p>
        <?php else: ?>
            <table class="table table-hover">
                <thead><tr><th>№</th><th>Клиент</th><th>Товар</th><th>Сумма</th><th>Выкуп</th><th></th></tr></thead>
                <tbody>
                    <?php $__currentLoopData = $pawnContracts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($c->contract_number); ?></td>
                        <td><?php echo e($c->client->full_name); ?></td>
                        <td><?php echo e($c->item->name); ?></td>
                        <td><?php echo e(number_format($c->loan_amount, 0, '', ' ')); ?> ₽</td>
                        <td><?php if($c->is_redeemed): ?><span class="badge bg-success">Да</span><?php else: ?><span class="badge bg-warning">Нет</span><?php endif; ?></td>
                        <td><a href="<?php echo e(route('pawn-contracts.show', $c)); ?>" class="btn btn-sm btn-outline-primary">Открыть</a></td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
            <?php echo e($pawnContracts->withQueryString()->links()); ?>

        <?php endif; ?>
    </div>
    <div class="tab-pane fade" id="commission" role="tabpanel">
        <p class="text-muted small">Договоры комиссии, где вы указаны приёмщиком (оценщиком).</p>
        <?php if($commissionContracts->isEmpty()): ?>
            <p class="text-muted">Нет договоров.</p>
        <?php else: ?>
            <table class="table table-hover">
                <thead><tr><th>№</th><th>Клиент</th><th>Товар</th><th>Цена</th><th>Продано</th><th></th></tr></thead>
                <tbody>
                    <?php $__currentLoopData = $commissionContracts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($c->contract_number); ?></td>
                        <td><?php echo e($c->client->full_name); ?></td>
                        <td><?php echo e($c->item->name); ?></td>
                        <td><?php echo e($c->seller_price ? number_format($c->seller_price, 0, '', ' ') . ' ₽' : '—'); ?></td>
                        <td><?php if($c->is_sold): ?><span class="badge bg-success">Да</span><?php else: ?><span class="badge bg-warning">Нет</span><?php endif; ?></td>
                        <td><a href="<?php echo e(route('commission-contracts.show', $c)); ?>" class="btn btn-sm btn-outline-primary">Открыть</a></td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
            <?php echo e($commissionContracts->withQueryString()->links()); ?>

        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/profile/show.blade.php ENDPATH**/ ?>