<?php $__env->startSection('title', 'Договор ' . $pawnContract->contract_number); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Договор залога <?php echo e($pawnContract->contract_number); ?></h1>
    <div>
        <a href="<?php echo e(route('pawn-contracts.print', $pawnContract)); ?>" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-printer"></i> Печать</a>
        <?php if(!$pawnContract->is_redeemed && auth()->user()->canProcessSales()): ?>
        <form action="<?php echo e(route('pawn-contracts.redeem', $pawnContract)); ?>" method="post" class="d-inline" onsubmit="return confirm('Оформить выкуп?')"><?php echo csrf_field(); ?><button type="submit" class="btn btn-success">Оформить выкуп</button></form>
        <?php endif; ?>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Клиент</div>
            <div class="card-body">
                <p><a href="<?php echo e(route('clients.show', $pawnContract->client)); ?>"><?php echo e($pawnContract->client->full_name); ?></a></p>
                <p>Телефон: <?php echo e($pawnContract->client->phone); ?></p>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">Условия</div>
            <div class="card-body">
                <p><strong>Сумма займа:</strong> <?php echo e(number_format($pawnContract->loan_amount, 0, '', ' ')); ?> ₽</p>
                <p><strong>Процент:</strong> <?php echo e($pawnContract->loan_percent ?? 0); ?>%</p>
                <p><strong>Сумма выкупа:</strong> <?php echo e(number_format($pawnContract->buyback_amount ?? 0, 0, '', ' ')); ?> ₽</p>
                <p><strong>Дата займа:</strong> <?php echo e(\Carbon\Carbon::parse($pawnContract->loan_date)->format('d.m.Y')); ?></p>
                <p><strong>Срок до:</strong> <?php echo e(\Carbon\Carbon::parse($pawnContract->expiry_date)->format('d.m.Y')); ?></p>
                <p><strong>Принял:</strong> <?php echo e($pawnContract->appraiser?->name ?? '—'); ?></p>
                <?php if($pawnContract->is_redeemed): ?>
                <p><strong>Выкуплен:</strong> <?php echo e($pawnContract->redeemed_at ? \Carbon\Carbon::parse($pawnContract->redeemed_at)->format('d.m.Y H:i') : '—'); ?></p>
                <p><strong>Оформил:</strong> <?php echo e($pawnContract->redeemedByUser?->name ?? '—'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Товар</div>
            <div class="card-body">
                <p><a href="<?php echo e(route('items.show', $pawnContract->item)); ?>"><?php echo e($pawnContract->item->name); ?></a></p>
                <p>Штрихкод: <code><?php echo e($pawnContract->item->barcode); ?></code></p>
                <p>Магазин: <?php echo e($pawnContract->store->name); ?></p>
            </div>
        </div>
    </div>
</div>
<a href="<?php echo e(route('pawn-contracts.index')); ?>" class="btn btn-secondary">К списку договоров</a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/pawn-contracts/show.blade.php ENDPATH**/ ?>