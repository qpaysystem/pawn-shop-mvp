<?php $__env->startSection('title', 'Оборотно-сальдовая ведомость'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4"><i class="bi bi-table me-2"></i>Оборотно-сальдовая ведомость</h1>

<div class="mb-3">
    <a href="<?php echo e(route('chart-of-accounts.index')); ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> План счетов</a>
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
    <div class="col-auto">
        <label class="form-label">Клиент</label>
        <select name="client_id" class="form-select form-select-sm" style="width:auto; max-width:220px" onchange="this.form.submit()">
            <option value="">Все</option>
            <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($c->id); ?>" <?php echo e($clientId == $c->id ? 'selected' : ''); ?>><?php echo e($c->last_name); ?> <?php echo e($c->first_name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="col-auto align-self-end">
        <button type="submit" class="btn btn-primary btn-sm">Показать</button>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Оборотно-сальдовая ведомость</span>
        <span class="small text-muted"><?php echo e($dateFrom); ?> — <?php echo e($dateTo); ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Счёт</th>
                        <th>Наименование</th>
                        <th class="text-end">Сальдо на начало</th>
                        <th class="text-end">Оборот дебет</th>
                        <th class="text-end">Оборот кредит</th>
                        <th class="text-end">Сальдо на конец</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr>
                        <td><strong><?php echo e($row->account->code); ?></strong></td>
                        <td><?php echo e($row->account->name); ?></td>
                        <td class="text-end"><?php echo e(number_format($row->balance_before, 2, ',', ' ')); ?></td>
                        <td class="text-end"><?php echo e(number_format($row->debit, 2, ',', ' ')); ?></td>
                        <td class="text-end"><?php echo e(number_format($row->credit, 2, ',', ' ')); ?></td>
                        <td class="text-end"><strong><?php echo e(number_format($row->balance_after, 2, ',', ' ')); ?></strong></td>
                        <td>
                            <a href="<?php echo e(route('chart-of-accounts.show', ['account' => $row->account, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'store_id' => $storeId, 'client_id' => $clientId])); ?>" class="btn btn-sm btn-outline-primary">Карточка</a>
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr>
                        <td colspan="7" class="text-muted text-center py-4">Нет данных за период. Проводки создаются при кассовых операциях, выдаче займов, скупке и продажах.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/chart-of-accounts/turnover-balance.blade.php ENDPATH**/ ?>