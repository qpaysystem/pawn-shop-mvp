<?php $__env->startSection('title', 'Карточка счёта ' . $account->code); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4"><i class="bi bi-journal-ruled me-2"></i>Карточка счёта <?php echo e($account->code); ?> — <?php echo e($account->name); ?></h1>

<div class="mb-3">
    <a href="<?php echo e(route('chart-of-accounts.index')); ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> План счетов</a>
    <a href="<?php echo e(route('chart-of-accounts.turnover-balance')); ?>" class="btn btn-outline-primary">Оборотно-сальдовая ведомость</a>
</div>

<form method="get" class="row g-3 mb-4">
    <div class="col-auto">
        <label class="form-label">Дата с</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo e($dateFrom); ?>" onchange="this.form.submit()">
    </div>
    <div class="col-auto">
        <label class="form-label">Дата по</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo e($dateTo); ?>" onchange="this.form.submit()">
    </div>
    <?php if($stores->isNotEmpty()): ?>
    <div class="col-auto">
        <label class="form-label">Магазин</label>
        <select name="store_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все</option>
            <?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($s->id); ?>" <?php echo e($storeId == $s->id ? 'selected' : ''); ?>><?php echo e($s->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <?php endif; ?>
</form>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Итоги за период</span>
        <span class="small text-muted"><?php echo e($dateFrom); ?> — <?php echo e($dateTo); ?></span>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <strong>Сальдо на начало</strong>
                <div class="h5"><?php echo e(number_format($balanceBefore, 2, ',', ' ')); ?> ₽</div>
            </div>
            <div class="col-md-3">
                <strong>Оборот по дебету</strong>
                <div class="h5 text-success"><?php echo e(number_format($totalDebit, 2, ',', ' ')); ?> ₽</div>
            </div>
            <div class="col-md-3">
                <strong>Оборот по кредиту</strong>
                <div class="h5 text-danger"><?php echo e(number_format($totalCredit, 2, ',', ' ')); ?> ₽</div>
            </div>
            <div class="col-md-3">
                <strong>Сальдо на конец</strong>
                <div class="h5"><?php echo e(number_format($balanceAfter, 2, ',', ' ')); ?> ₽</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Движения по счёту</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Дата</th>
                        <th>Документ</th>
                        <th>Магазин</th>
                        <th class="text-end">Дебет</th>
                        <th class="text-end">Кредит</th>
                        <th>Комментарий</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $entries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><?php echo e($e->entry_date ? \Carbon\Carbon::parse($e->entry_date)->format('d.m.Y') : '—'); ?></td>
                        <td>
                            <?php if($e->document_type && $e->document_id): ?>
                                <?php echo e($e->document_type); ?> #<?php echo e($e->document_id); ?>

                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($e->store?->name ?? '—'); ?></td>
                        <td class="text-end"><?php echo e($e->debit > 0 ? number_format($e->debit, 2, ',', ' ') : '—'); ?></td>
                        <td class="text-end"><?php echo e($e->credit > 0 ? number_format($e->credit, 2, ',', ' ') : '—'); ?></td>
                        <td class="small text-muted"><?php echo e(Str::limit($e->comment, 50)); ?></td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="6" class="text-muted text-center py-4">Нет движений за выбранный период.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if($entries->hasPages()): ?>
    <div class="card-footer">
        <?php echo e($entries->withQueryString()->links()); ?>

    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/chart-of-accounts/show.blade.php ENDPATH**/ ?>