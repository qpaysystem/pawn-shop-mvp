<?php $__env->startSection('title', 'План бухгалтерских счетов'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4"><i class="bi bi-journal-ruled me-2"></i>План бухгалтерских счетов</h1>

<div class="mb-3">
    <a href="<?php echo e(route('chart-of-accounts.turnover-balance')); ?>" class="btn btn-primary"><i class="bi bi-table"></i> Оборотно-сальдовая ведомость</a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Номер счёта</th>
                    <th>Название счёта</th>
                    <th>Назначение / Аналитика</th>
                    <th>Тип</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><strong><?php echo e($account->code); ?></strong></td>
                    <td><?php echo e($account->name); ?></td>
                    <td class="text-muted small"><?php echo e($account->description ?? '—'); ?></td>
                    <td>
                        <?php if($account->type === 'active'): ?>
                            <span class="badge bg-primary">Активный</span>
                        <?php elseif($account->type === 'passive'): ?>
                            <span class="badge bg-secondary">Пассивный</span>
                        <?php else: ?>
                            <span class="badge bg-info">Активно-пассивный</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo e(route('chart-of-accounts.show', $account)); ?>" class="btn btn-sm btn-outline-primary">Карточка счёта</a>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    </div>
</div>

<?php if($accounts->isEmpty()): ?>
    <p class="text-muted mt-3">Счета не найдены. Выполните: <code>php artisan db:seed --class=AccountsSeeder</code></p>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/chart-of-accounts/index.blade.php ENDPATH**/ ?>