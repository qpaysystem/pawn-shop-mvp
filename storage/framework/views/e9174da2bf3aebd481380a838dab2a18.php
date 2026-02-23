<?php $__env->startSection('title', 'Договор скупки ' . $purchaseContract->contract_number); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Договор скупки <?php echo e($purchaseContract->contract_number); ?></h1>
    <div>
        <a href="<?php echo e(route('purchase-contracts.print', $purchaseContract)); ?>" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-printer"></i> Печать</a>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Продавец (клиент)</div>
            <div class="card-body">
                <p><a href="<?php echo e(route('clients.show', $purchaseContract->client)); ?>"><?php echo e($purchaseContract->client->full_name); ?></a></p>
                <p>Телефон: <?php echo e($purchaseContract->client->phone); ?></p>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">Условия скупки</div>
            <div class="card-body">
                <p><strong>Сумма скупки:</strong> <?php echo e(number_format($purchaseContract->purchase_amount, 0, '', ' ')); ?> ₽</p>
                <p><strong>Дата:</strong> <?php echo e($purchaseContract->purchase_date ? \Carbon\Carbon::parse($purchaseContract->purchase_date)->format('d.m.Y') : '—'); ?></p>
                <p><strong>Принял:</strong> <?php echo e($purchaseContract->appraiser?->name ?? '—'); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Товар</div>
            <div class="card-body">
                <p><a href="<?php echo e(route('items.show', $purchaseContract->item)); ?>"><?php echo e($purchaseContract->item->name); ?></a></p>
                <p>Штрихкод: <code><?php echo e($purchaseContract->item->barcode); ?></code></p>
                <p>Магазин: <?php echo e($purchaseContract->store->name); ?></p>
            </div>
        </div>
    </div>
</div>
<a href="<?php echo e(route('purchase-contracts.index')); ?>" class="btn btn-secondary">К списку договоров</a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/purchase-contracts/show.blade.php ENDPATH**/ ?>