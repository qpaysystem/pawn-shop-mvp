<?php $__env->startSection('title', 'Касса'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4">Кассовые операции</h1>

<?php if(auth()->user()->canProcessSales()): ?>
<div class="mb-3">
    <a href="<?php echo e(route('cash.create', $store ? ['store_id' => $store->id] : [])); ?>" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Новый приход / расход</a>
    <a href="<?php echo e(route('cash.report')); ?>" class="btn btn-outline-primary"><i class="bi bi-bar-chart"></i> Отчёт по кассам</a>
</div>
<?php endif; ?>

<?php if($stores->isEmpty()): ?>
    <p class="text-muted">Нет доступных магазинов.</p>
<?php else: ?>
<form method="get" class="row g-3 mb-4">
    <div class="col-auto">
        <label class="form-label">Магазин</label>
        <select name="store_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($s->id); ?>" <?php echo e(($store && $store->id == $s->id) ? 'selected' : ''); ?>><?php echo e($s->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label">Клиент</label>
        <select name="client_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все</option>
            <?php $__currentLoopData = \App\Models\Client::orderBy('full_name')->get(['id','full_name']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($c->id); ?>" <?php echo e(request('client_id') == $c->id ? 'selected' : ''); ?>><?php echo e($c->full_name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label">Тип</label>
        <select name="direction" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все</option>
            <option value="income" <?php echo e(request('direction') === 'income' ? 'selected' : ''); ?>>Приход</option>
            <option value="expense" <?php echo e(request('direction') === 'expense' ? 'selected' : ''); ?>>Расход</option>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label">Дата с</label>
        <input type="date" name="date_from" class="form-control form-control-sm" style="width:auto" value="<?php echo e(request('date_from')); ?>" onchange="this.form.submit()">
    </div>
    <div class="col-auto">
        <label class="form-label">Дата по</label>
        <input type="date" name="date_to" class="form-control form-control-sm" style="width:auto" value="<?php echo e(request('date_to')); ?>" onchange="this.form.submit()">
    </div>
    <?php if(request()->hasAny(['direction','date_from','date_to','client_id'])): ?>
    <div class="col-auto align-self-end">
        <a href="<?php echo e(route('cash.index', ['store_id' => $store?->id])); ?>" class="btn btn-sm btn-outline-secondary">Сбросить</a>
    </div>
    <?php endif; ?>
</form>

<?php if($store || isset($filterClient)): ?>
<div class="card mb-4">
    <div class="card-body py-3">
        <?php if($store): ?>
        <strong>Кассовый баланс (<?php echo e($store->name); ?>):</strong>
        <span class="fs-4 ms-2 <?php echo e($balance >= 0 ? 'text-success' : 'text-danger'); ?>"><?php echo e(number_format($balance, 0, ',', ' ')); ?> ₽</span>
        <?php endif; ?>
        <?php if(isset($filterClient) && $filterClient): ?>
        <span class="ms-4">|</span>
        <strong class="ms-4">Баланс клиента <?php echo e($filterClient->full_name); ?>:</strong>
        <span class="fs-4 ms-2 <?php echo e(($clientBalance ?? 0) >= 0 ? 'text-success' : 'text-danger'); ?>"><?php echo e(number_format($clientBalance ?? 0, 0, ',', ' ')); ?> ₽</span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<table class="table table-hover">
    <thead>
        <tr>
            <th>Дата</th>
            <th>№ документа</th>
            <?php if(isset($filterClient) && $filterClient): ?>
                <th>Магазин</th>
            <?php else: ?>
                <th>Клиент</th>
            <?php endif; ?>
            <th>Вид операции</th>
            <th>Приход</th>
            <th>Расход</th>
            <th>Комментарий</th>
            <th>Создал</th>
            <?php if(auth()->user()->canProcessSales()): ?><th></th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
            <td><?php echo e(\Carbon\Carbon::parse($d->document_date)->format('d.m.Y')); ?></td>
            <td><a href="<?php echo e(route('cash.show', $d)); ?>"><?php echo e($d->document_number); ?></a></td>
            <td>
                <?php if(isset($filterClient) && $filterClient): ?>
                    <?php echo e($d->store?->name ?? '—'); ?>

                <?php elseif($d->client): ?>
                    <a href="<?php echo e(route('clients.show', $d->client)); ?>"><?php echo e($d->client->full_name); ?></a>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <td>
                <?php echo e($d->operationType->name); ?>

                <?php if($d->isTransfer() && $d->targetStore): ?>
                    <br><small class="text-muted"><?php echo e($d->store?->name); ?> → <?php echo e($d->targetStore->name); ?></small>
                <?php endif; ?>
            </td>
            <td><?php if($d->isIncome()): ?><?php echo e(number_format($d->amount, 0, ',', ' ')); ?> ₽<?php else: ?>—<?php endif; ?></td>
            <td><?php if($d->isExpense()): ?><?php echo e(number_format($d->amount, 0, ',', ' ')); ?> ₽<?php else: ?>—<?php endif; ?></td>
            <td class="text-muted small"><?php echo e(Str::limit($d->comment, 50)); ?></td>
            <td class="small"><?php echo e($d->createdByUser?->name ?? '—'); ?></td>
            <?php if(auth()->user()->canProcessSales()): ?>
            <td>
                <a href="<?php echo e(route('cash.edit', $d)); ?>" class="btn btn-sm btn-outline-primary me-1">Изменить</a>
                <form action="<?php echo e(route('cash.destroy', $d)); ?>" method="post" class="d-inline" onsubmit="return confirm('Удалить документ?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button></form>
            </td>
            <?php endif; ?>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="<?php echo e(auth()->user()->canProcessSales() ? 9 : 8); ?>" class="text-muted text-center py-4">Нет документов за выбранный период.</td></tr>
        <?php endif; ?>
    </tbody>
</table>
<?php echo e($documents->links()); ?>

<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/cash/index.blade.php ENDPATH**/ ?>