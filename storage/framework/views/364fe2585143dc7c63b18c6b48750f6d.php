<?php $__env->startSection('title', $client->full_name); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><?php echo e($client->full_name); ?></h1>
    <div>
        <a href="<?php echo e(route('clients.edit', $client)); ?>" class="btn btn-outline-primary">Изменить</a>
    </div>
</div>
<div class="card mb-4">
    <div class="card-body">
        <p><strong>Телефон:</strong> <?php echo e($client->phone); ?></p>
        <?php if($client->email): ?><p><strong>Email:</strong> <?php echo e($client->email); ?></p><?php endif; ?>
        <?php if($client->passport_data): ?><p><strong>Паспорт:</strong> <?php echo e($client->passport_data); ?></p><?php endif; ?>
        <?php if($client->notes): ?><p><strong>Заметки:</strong> <?php echo e($client->notes); ?></p><?php endif; ?>
        <?php if($client->blacklist_flag): ?><p><span class="badge bg-danger">В чёрном списке</span></p><?php endif; ?>
    </div>
</div>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Данные из 1С</strong>
        <form method="post" action="<?php echo e(route('clients.sync-lmb', $client)); ?>" class="d-inline">
            <?php echo csrf_field(); ?>
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-cloud-download"></i> Загрузить из 1С</button>
        </form>
    </div>
    <div class="card-body">
        <?php if($client->lmb_data): ?>
            <p class="mb-1"><strong>Код в 1С (user_uid):</strong> <?php echo e($client->lmb_data['user_uid'] ?? '—'); ?></p>
            <p class="mb-1"><strong>ФИО / first_name:</strong> <?php echo e($client->lmb_data['first_name'] ?? '—'); ?></p>
            <p class="mb-1"><strong>Имя (second_name):</strong> <?php echo e($client->lmb_data['second_name'] ?? '—'); ?></p>
            <p class="mb-1"><strong>Отчество (last_name):</strong> <?php echo e($client->lmb_data['last_name'] ?? '—'); ?></p>
            <p class="mb-0"><strong>Телефон:</strong> <?php echo e($client->lmb_data['phone'] ?? '—'); ?></p>
        <?php else: ?>
            <p class="text-muted mb-0">Данные не загружались. Нажмите «Загрузить из 1С», чтобы получить данные контрагента по номеру телефона.</p>
        <?php endif; ?>
    </div>
</div>
<div class="card mb-4">
    <div class="card-body py-3">
        <strong>Кассовый баланс клиента:</strong>
        <span class="fs-5 ms-2 <?php echo e($client->cash_balance >= 0 ? 'text-success' : 'text-danger'); ?>"><?php echo e(number_format($client->cash_balance, 0, ',', ' ')); ?> ₽</span>
        <span class="text-muted small ms-2">(приходы − расходы по операциям с клиентом)</span>
        <a href="<?php echo e(route('cash.index', ['client_id' => $client->id])); ?>" class="btn btn-sm btn-outline-primary ms-2">Операции по кассе →</a>
    </div>
</div>
<h5 class="mb-3">Договоры залога</h5>
<?php if($client->pawnContracts->isEmpty()): ?>
    <p class="text-muted">Нет договоров залога.</p>
<?php else: ?>
    <table class="table table-sm">
        <thead><tr><th>№ договора</th><th>Товар</th><th>Сумма займа</th><th>Сумма на выкуп</th><th>Выкуп</th><th></th></tr></thead>
        <tbody>
            <?php $__currentLoopData = $client->pawnContracts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e($pc->contract_number); ?></td>
                <td><?php echo e($pc->item->name); ?></td>
                <td><?php echo e(number_format($pc->loan_amount, 0, '', ' ')); ?> ₽</td>
                <td><?php echo e(number_format($pc->redemption_amount, 0, '', ' ')); ?> ₽</td>
                <td><?php if($pc->is_redeemed): ?><span class="badge bg-success">Выкуплен</span><?php else: ?><span class="badge bg-warning">Активен</span><?php endif; ?></td>
                <td><a href="<?php echo e(route('pawn-contracts.show', $pc)); ?>">Подробнее</a></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
<?php endif; ?>
<h5 class="mb-3 mt-4">Договоры комиссии</h5>
<?php if($client->commissionContracts->isEmpty()): ?>
    <p class="text-muted">Нет договоров комиссии.</p>
<?php else: ?>
    <table class="table table-sm">
        <thead><tr><th>№ договора</th><th>Товар</th><th>Цена продажи</th><th>Продано</th><th></th></tr></thead>
        <tbody>
            <?php $__currentLoopData = $client->commissionContracts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e($cc->contract_number); ?></td>
                <td><?php echo e($cc->item->name); ?></td>
                <td><?php echo e($cc->seller_price ? number_format($cc->seller_price, 0, '', ' ') . ' ₽' : '—'); ?></td>
                <td><?php if($cc->is_sold): ?><span class="badge bg-success">Да</span><?php else: ?><span class="badge bg-warning">Нет</span><?php endif; ?></td>
                <td><a href="<?php echo e(route('commission-contracts.show', $cc)); ?>">Подробнее</a></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
<?php endif; ?>
<h5 class="mb-3 mt-4">Договоры скупки</h5>
<?php if($client->purchaseContracts->isEmpty()): ?>
    <p class="text-muted">Нет договоров скупки.</p>
<?php else: ?>
    <table class="table table-sm">
        <thead><tr><th>№ договора</th><th>Товар</th><th>Сумма скупки</th><th>Дата</th><th></th></tr></thead>
        <tbody>
            <?php $__currentLoopData = $client->purchaseContracts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $puc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e($puc->contract_number); ?></td>
                <td><?php echo e($puc->item->name); ?></td>
                <td><?php echo e(number_format($puc->purchase_amount, 0, '', ' ')); ?> ₽</td>
                <td><?php echo e(\Carbon\Carbon::parse($puc->purchase_date)->format('d.m.Y')); ?></td>
                <td><a href="<?php echo e(route('purchase-contracts.show', $puc)); ?>">Подробнее</a></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
<?php endif; ?>
<h5 class="mb-3 mt-4">Обращения (колл-центр)</h5>
<?php if($client->callCenterContacts->isEmpty()): ?>
    <p class="text-muted">Нет обращений.</p>
<?php else: ?>
    <table class="table table-sm">
        <thead><tr><th>Дата</th><th>Канал</th><th>Исход</th><th>Сделка</th><th></th></tr></thead>
        <tbody>
            <?php $__currentLoopData = $client->callCenterContacts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ccc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
                <td><?php echo e($ccc->contact_date ? \Carbon\Carbon::parse($ccc->contact_date)->format('d.m.Y H:i') : '—'); ?></td>
                <td><?php echo e($ccc->channel_label); ?></td>
                <td><?php echo e($ccc->outcome_label); ?></td>
                <td>
                    <?php if($ccc->pawn_contract_id): ?>
                        <a href="<?php echo e(route('pawn-contracts.show', $ccc->pawnContract)); ?>"><?php echo e($ccc->pawnContract->contract_number); ?></a>
                    <?php elseif($ccc->purchase_contract_id): ?>
                        <a href="<?php echo e(route('purchase-contracts.show', $ccc->purchaseContract)); ?>"><?php echo e($ccc->purchaseContract->contract_number); ?></a>
                    <?php elseif($ccc->commission_contract_id): ?>
                        <a href="<?php echo e(route('commission-contracts.show', $ccc->commissionContract)); ?>"><?php echo e($ccc->commissionContract->contract_number); ?></a>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td><a href="<?php echo e(route('call-center.show', $ccc)); ?>">Подробнее</a></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
    </table>
<?php endif; ?>
<a href="<?php echo e(route('call-center.create', ['client_id' => $client->id])); ?>" class="btn btn-sm btn-outline-primary">Зафиксировать обращение</a>
<a href="<?php echo e(route('call-center.index', ['client_id' => $client->id])); ?>" class="btn btn-sm btn-outline-secondary">Все обращения клиента</a>
<div class="mt-3">
<a href="<?php echo e(route('clients.index')); ?>" class="btn btn-secondary">К списку клиентов</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/clients/show.blade.php ENDPATH**/ ?>