<?php $__env->startSection('title', $item->name); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><?php echo e($item->name); ?></h1>
    <div>
        <?php if(auth()->user()->canManageStorage()): ?>
        <a href="<?php echo e(route('items.edit', $item)); ?>" class="btn btn-outline-primary">Изменить</a>
        <?php endif; ?>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-body">
                <p><strong>Штрихкод:</strong> <code><?php echo e($item->barcode); ?></code></p>
                <p><strong>Магазин:</strong> <?php echo e($item->store->name); ?></p>
                <p><strong>Статус:</strong> <?php if($item->status): ?><span class="badge" <?php if($item->status->color): ?> style="background-color:<?php echo e($item->status->color); ?>" <?php endif; ?>><?php echo e($item->status->name); ?></span><?php else: ?>—<?php endif; ?></p>
                <p><strong>Место хранения:</strong> <?php echo e($item->storageLocation?->name ?? '—'); ?></p>
                <p><strong>Категория:</strong> <?php echo e($item->category?->name ?? '—'); ?></p>
                <p><strong>Бренд:</strong> <?php echo e($item->brand?->name ?? '—'); ?></p>
                <p><strong>Оценочная стоимость:</strong> <?php echo e($item->initial_price ? number_format($item->initial_price, 0, '', ' ') . ' ₽' : '—'); ?></p>
                <p><strong>Текущая цена:</strong> <?php echo e($item->current_price ? number_format($item->current_price, 0, '', ' ') . ' ₽' : '—'); ?></p>
                <?php if($item->description): ?><p><strong>Описание:</strong><br><?php echo e($item->description); ?></p><?php endif; ?>
            </div>
        </div>
        <?php if($item->photos && count($item->photos) > 0): ?>
        <div class="card mb-4">
            <div class="card-header">Фото</div>
            <div class="card-body d-flex flex-wrap gap-2">
                <?php $__currentLoopData = $item->photos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $path): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e(asset('storage/' . $path)); ?>" target="_blank"><img src="<?php echo e(asset('storage/' . $path)); ?>" alt="" style="max-height:120px; max-width:120px; object-fit:cover;" class="rounded"></a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-md-6">
        <?php if($item->pawnContract): ?>
        <div class="card mb-4">
            <div class="card-header">Договор залога</div>
            <div class="card-body">
                <p><strong>№:</strong> <?php echo e($item->pawnContract->contract_number); ?></p>
                <p><strong>Клиент:</strong> <a href="<?php echo e(route('clients.show', $item->pawnContract->client)); ?>"><?php echo e($item->pawnContract->client->full_name); ?></a></p>
                <p><strong>Сумма займа:</strong> <?php echo e(number_format($item->pawnContract->loan_amount, 0, '', ' ')); ?> ₽</p>
                <p><strong>Выкуп:</strong> <?php echo e(number_format($item->pawnContract->buyback_amount ?? 0, 0, '', ' ')); ?> ₽</p>
                <p><strong>Срок до:</strong> <?php echo e(\Carbon\Carbon::parse($item->pawnContract->expiry_date)->format('d.m.Y')); ?></p>
                <p><?php if($item->pawnContract->is_redeemed): ?><span class="badge bg-success">Выкуплен</span><?php else: ?><span class="badge bg-warning">Активен</span><?php endif; ?></p>
                <a href="<?php echo e(route('pawn-contracts.show', $item->pawnContract)); ?>" class="btn btn-sm btn-outline-primary">Подробнее</a>
                <a href="<?php echo e(route('pawn-contracts.print', $item->pawnContract)); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">Печать</a>
            </div>
        </div>
        <?php endif; ?>
        <?php if($item->commissionContract): ?>
        <div class="card mb-4">
            <div class="card-header">Договор комиссии</div>
            <div class="card-body">
                <p><strong>№:</strong> <?php echo e($item->commissionContract->contract_number); ?></p>
                <p><strong>Клиент (комитент):</strong> <a href="<?php echo e(route('clients.show', $item->commissionContract->client)); ?>"><?php echo e($item->commissionContract->client->full_name); ?></a></p>
                <p><strong>Цена продажи:</strong> <?php echo e($item->commissionContract->seller_price ? number_format($item->commissionContract->seller_price, 0, '', ' ') . ' ₽' : '—'); ?></p>
                <p><?php if($item->commissionContract->is_sold): ?><span class="badge bg-success">Продан</span><?php else: ?><span class="badge bg-warning">Не продан</span><?php endif; ?></p>
                <a href="<?php echo e(route('commission-contracts.show', $item->commissionContract)); ?>" class="btn btn-sm btn-outline-primary">Подробнее</a>
                <a href="<?php echo e(route('commission-contracts.print', $item->commissionContract)); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">Печать</a>
            </div>
        </div>
        <?php endif; ?>
        <?php if($item->purchaseContract): ?>
        <div class="card mb-4">
            <div class="card-header">Договор скупки</div>
            <div class="card-body">
                <p><strong>№:</strong> <?php echo e($item->purchaseContract->contract_number); ?></p>
                <p><strong>Продавец:</strong> <a href="<?php echo e(route('clients.show', $item->purchaseContract->client)); ?>"><?php echo e($item->purchaseContract->client->full_name); ?></a></p>
                <p><strong>Сумма скупки:</strong> <?php echo e(number_format($item->purchaseContract->purchase_amount, 0, '', ' ')); ?> ₽</p>
                <p><strong>Дата:</strong> <?php echo e(\Carbon\Carbon::parse($item->purchaseContract->purchase_date)->format('d.m.Y')); ?></p>
                <a href="<?php echo e(route('purchase-contracts.show', $item->purchaseContract)); ?>" class="btn btn-sm btn-outline-primary">Подробнее</a>
                <a href="<?php echo e(route('purchase-contracts.print', $item->purchaseContract)); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">Печать</a>
            </div>
        </div>
        <?php endif; ?>
        <div class="card mb-4">
            <div class="card-header">История статусов</div>
            <div class="card-body">
                <?php $__empty_1 = true; $__currentLoopData = $item->statusHistory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $h): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="small text-muted">
                    <?php echo e($h->created_at ? \Carbon\Carbon::parse($h->created_at)->format('d.m.Y H:i') : '—'); ?>: <?php echo e($h->oldStatus?->name ?? '—'); ?> → <?php echo e($h->newStatus?->name ?? '—'); ?>

                    <?php if($h->changedByUser): ?> (<?php echo e($h->changedByUser->name); ?>) <?php endif; ?>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="text-muted mb-0">Нет записей.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<a href="<?php echo e(route('items.index')); ?>" class="btn btn-secondary">К списку товаров</a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/items/show.blade.php ENDPATH**/ ?>