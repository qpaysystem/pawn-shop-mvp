<?php $__env->startSection('title', 'Колл-центр'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4">Колл-центр</h1>

<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <a href="<?php echo e(route('call-center.create')); ?>" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Зафиксировать обращение</a>
    <a href="<?php echo e(route('call-center.analytics')); ?>" class="btn btn-outline-primary"><i class="bi bi-bar-chart"></i> Аналитика</a>
    <form method="post" action="<?php echo e(route('call-center.sync-mts-calls')); ?>" class="d-inline">
        <?php echo csrf_field(); ?>
        <select name="days" class="form-select form-select-sm d-inline-block" style="width:auto">
            <option value="1">За последний день</option>
            <option value="7">За 7 дней</option>
            <option value="30">За 30 дней</option>
        </select>
        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-telephone-inbound"></i> Загрузить звонки с MTS</button>
    </form>
    <form method="post" action="<?php echo e(route('call-center.sync-mts-recordings')); ?>" class="d-inline">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-mic"></i> Загрузить записи (за 7 дней)</button>
    </form>
    <form method="post" action="<?php echo e(route('call-center.clear-mts-contacts')); ?>" class="d-inline" onsubmit="return confirm('Удалить все телефонные обращения, загруженные из MTS?');">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Очистить звонки MTS</button>
    </form>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="channel" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все каналы</option>
            <?php $__currentLoopData = \App\Models\CallCenterContact::CHANNELS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($k); ?>" <?php echo e(request('channel') === $k ? 'selected' : ''); ?>><?php echo e($v); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="col-auto">
        <select name="call_status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все статусы вызова</option>
            <?php $__currentLoopData = \App\Models\CallCenterContact::CALL_STATUSES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($k); ?>" <?php echo e(request('call_status') === $k ? 'selected' : ''); ?>><?php echo e($v); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="col-auto">
        <select name="outcome" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все исходы</option>
            <?php $__currentLoopData = \App\Models\CallCenterContact::OUTCOMES; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($k); ?>" <?php echo e(request('outcome') === $k ? 'selected' : ''); ?>><?php echo e($v); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="col-auto">
        <input type="date" name="date_from" class="form-control form-control-sm" style="width:auto" value="<?php echo e(request('date_from')); ?>" onchange="this.form.submit()" placeholder="Дата с">
    </div>
    <div class="col-auto">
        <input type="date" name="date_to" class="form-control form-control-sm" style="width:auto" value="<?php echo e(request('date_to')); ?>" onchange="this.form.submit()" placeholder="Дата по">
    </div>
</form>

<?php if($contacts->isEmpty()): ?>
    <p class="text-muted">Нет обращений за выбранный период.</p>
<?php else: ?>
<table class="table table-hover">
    <thead>
        <tr>
            <th>Дата</th>
            <th>Канал</th>
            <th>Длительность</th>
            <th>Контакт</th>
            <th>Магазин</th>
            <th>Исход</th>
            <th>Сделка</th>
            <th>Запись</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php $__currentLoopData = $contacts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><?php echo e($c->contact_date ? \Carbon\Carbon::parse($c->contact_date)->format('d.m.Y H:i') : '—'); ?></td>
            <td>
                <span class="badge bg-secondary"><?php echo e($c->channel_label); ?></span>
                <?php if($c->direction === 'outgoing'): ?><span class="badge bg-light text-dark">исх.</span><?php endif; ?>
            </td>
            <td>
                <?php if($c->call_duration_sec !== null && $c->call_duration_sec > 1): ?>
                    <span class="badge bg-success"><?php echo e($c->call_duration_sec); ?> сек</span>
                <?php elseif($c->call_status === 'missed' || ($c->call_duration_sec !== null && $c->call_duration_sec <= 1)): ?>
                    <span class="badge bg-warning text-dark">Пропущен</span>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if($c->client_id): ?>
                    <a href="<?php echo e(route('clients.show', $c->client)); ?>"><?php echo e($c->client->full_name); ?></a>
                    <?php if($c->client->phone): ?><br><small class="text-muted"><?php echo e($c->client->phone); ?></small><?php endif; ?>
                <?php else: ?>
                    <?php echo e($c->contact_name ?: $c->contact_phone ?: '—'); ?>

                <?php endif; ?>
            </td>
            <td><?php echo e($c->store?->name ?? '—'); ?></td>
            <td><?php echo e($c->outcome_label); ?></td>
            <td>
                <?php if($c->pawn_contract_id): ?>
                    <a href="<?php echo e(route('pawn-contracts.show', $c->pawnContract)); ?>"><?php echo e($c->pawnContract->contract_number); ?></a>
                <?php elseif($c->purchase_contract_id): ?>
                    <a href="<?php echo e(route('purchase-contracts.show', $c->purchaseContract)); ?>"><?php echo e($c->purchaseContract->contract_number); ?></a>
                <?php elseif($c->commission_contract_id): ?>
                    <a href="<?php echo e(route('commission-contracts.show', $c->commissionContract)); ?>"><?php echo e($c->commissionContract->contract_number); ?></a>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <td>
                <?php if($c->recording_path): ?>
                    <a href="<?php echo e(route('call-center.show', $c)); ?>#recording" title="Слушать запись"><i class="bi bi-mic-fill text-success"></i></a>
                <?php elseif($c->ext_tracking_id): ?>
                    <span class="text-muted" title="Есть ID записи MTS, загрузите записи"><i class="bi bi-mic"></i></span>
                <?php else: ?>
                    —
                <?php endif; ?>
            </td>
            <td>
                <a href="<?php echo e(route('call-center.show', $c)); ?>" class="btn btn-sm btn-outline-secondary">Подробнее</a>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table>
<?php echo e($contacts->links()); ?>

<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/call-center/index.blade.php ENDPATH**/ ?>