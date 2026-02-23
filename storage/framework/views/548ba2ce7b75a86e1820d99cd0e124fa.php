<?php $__env->startSection('title', 'Дашборд'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4">Дашборд</h1>

<ul class="nav nav-tabs mb-3" id="dashboardTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">Обзор</button>
    </li>
    <?php if(auth()->user()->canCreateContracts()): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="accept-tab" data-bs-toggle="tab" data-bs-target="#accept" type="button" role="tab">Оформить залог</button>
    </li>
    <?php endif; ?>
    <?php if(auth()->user()->canProcessSales()): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="redeem-tab" data-bs-toggle="tab" data-bs-target="#redeem" type="button" role="tab">Сделать выкуп</button>
    </li>
    <?php endif; ?>
</ul>
<div class="tab-content" id="dashboardTabsContent">
    <div class="tab-pane fade show active" id="overview" role="tabpanel">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Товаров в магазине</h5>
                        <p class="display-6 mb-0"><?php echo e($itemsCount); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Активных договоров залога</h5>
                        <p class="display-6 mb-0"><?php echo e($activePawnCount); ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Активных договоров комиссии</h5>
                        <p class="display-6 mb-0"><?php echo e($activeCommissionCount); ?></p>
                    </div>
                </div>
            </div>
            <?php if(isset($totalCashBalance) && auth()->user()->canProcessSales()): ?>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Кассовый баланс</h5>
                        <p class="display-6 mb-0 <?php echo e($totalCashBalance >= 0 ? 'text-success' : 'text-danger'); ?>"><?php echo e(number_format($totalCashBalance ?? 0, 0, ',', ' ')); ?> ₽</p>
                        <a href="<?php echo e(route('cash.index')); ?>" class="small">Касса →</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if(auth()->user()->canCreateContracts()): ?>
    <div class="tab-pane fade" id="accept" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Принять товар в залог или комиссию</h5>
                <p class="card-text">Заполните форму приёма: выберите тип договора (залог или комиссия), укажите клиента и данные товара.</p>
                <a href="<?php echo e(route('accept.create')); ?>" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Оформить залог / комиссию</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if(auth()->user()->canProcessSales()): ?>
    <div class="tab-pane fade" id="redeem" role="tabpanel">
        <p class="text-muted small">Активные договоры залога, доступные для выкупа. Нажмите «Выкуп» для оформления.</p>
        <?php if(isset($activePawnForRedeem) && $activePawnForRedeem->isNotEmpty()): ?>
            <table class="table table-hover">
                <thead><tr><th>№ договора</th><th>Клиент</th><th>Товар</th><th>Сумма выкупа</th><th>Срок до</th><th></th></tr></thead>
                <tbody>
                    <?php $__currentLoopData = $activePawnForRedeem; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($c->contract_number); ?></td>
                        <td><?php echo e($c->client->full_name); ?></td>
                        <td><?php echo e($c->item->name); ?></td>
                        <td><?php echo e(number_format($c->buyback_amount ?? 0, 0, '', ' ')); ?> ₽</td>
                        <td><?php echo e($c->expiry_date ? \Carbon\Carbon::parse($c->expiry_date)->format('d.m.Y') : '—'); ?></td>
                        <td>
                            <form action="<?php echo e(route('pawn-contracts.redeem', $c)); ?>" method="post" class="d-inline" onsubmit="return confirm('Оформить выкуп?')"><?php echo csrf_field(); ?><button type="submit" class="btn btn-sm btn-success">Выкуп</button></form>
                            <a href="<?php echo e(route('pawn-contracts.show', $c)); ?>" class="btn btn-sm btn-outline-secondary">Подробнее</a>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
            <a href="<?php echo e(route('pawn-contracts.index')); ?>?redeemed=0" class="btn btn-outline-secondary">Все активные договоры залога →</a>
        <?php else: ?>
            <p class="text-muted">Нет активных договоров залога для выкупа.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/dashboard.blade.php ENDPATH**/ ?>