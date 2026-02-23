<?php $__env->startSection('title', 'Аналитика колл-центра'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4">Аналитика колл-центра</h1>

<form method="get" class="row g-2 mb-4">
    <div class="col-auto">
        <label class="form-label">Период</label>
        <div class="d-flex gap-2 align-items-end">
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo e($dateFrom); ?>" style="width:auto">
            <span>—</span>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo e($dateTo); ?>" style="width:auto">
            <button type="submit" class="btn btn-sm btn-primary">Применить</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Всего обращений</h6>
                <p class="display-6 mb-0"><?php echo e($totalContacts); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Звонки принято</h6>
                <p class="display-6 mb-0 text-success"><?php echo e($callsAccepted); ?></p>
                <small class="text-muted">длительность &gt; 1 сек</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Звонки пропущено</h6>
                <p class="display-6 mb-0 text-warning"><?php echo e($callsMissed); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Сделки: залог</h6>
                <p class="display-6 mb-0 text-primary"><?php echo e($convertedPawn); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Сделки: скупка</h6>
                <p class="display-6 mb-0 text-success"><?php echo e($convertedPurchase); ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Конверсия</h6>
                <p class="display-6 mb-0"><?php echo e($conversionRate); ?>%</p>
                <small class="text-muted"><?php echo e($totalDeals); ?> из <?php echo e($totalContacts); ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Сделки: комиссия</h6>
                <p class="display-6 mb-0"><?php echo e($convertedCommission); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">По каналам</div>
            <div class="card-body">
                <?php
                    $channelLabels = \App\Models\CallCenterContact::CHANNELS;
                ?>
                <?php $__empty_1 = true; $__currentLoopData = $byChannel; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ch => $cnt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span><?php echo e($channelLabels[$ch] ?? $ch); ?></span>
                        <strong><?php echo e($cnt); ?></strong>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="text-muted mb-0">Нет данных</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">По исходам</div>
            <div class="card-body">
                <?php
                    $outcomeLabels = \App\Models\CallCenterContact::OUTCOMES;
                ?>
                <?php $__empty_1 = true; $__currentLoopData = $byOutcome; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $out => $cnt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span><?php echo e($outcomeLabels[$out] ?? $out); ?></span>
                        <strong><?php echo e($cnt); ?></strong>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="text-muted mb-0">Нет данных</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mb-3">
    <a href="<?php echo e(route('call-center.index')); ?>" class="btn btn-secondary">← К списку обращений</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/call-center/analytics.blade.php ENDPATH**/ ?>