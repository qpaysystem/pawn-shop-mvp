<?php $__env->startSection('title', 'Выписки — ' . $bankAccount->name); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-file-earmark-text me-2"></i>Выписки: <?php echo e($bankAccount->name); ?></h1>
    <div>
        <a href="<?php echo e(route('bank-accounts.index')); ?>" class="btn btn-outline-secondary me-2">К счетам</a>
        <a href="<?php echo e(route('bank-accounts.statements.create', $bankAccount)); ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить выписку</a>
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Период</th><th>Начальное сальдо</th><th>Конечное сальдо</th><th>Файл</th><th></th></tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $statements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e(\Carbon\Carbon::parse($s->date_from)->format('d.m.Y')); ?> — <?php echo e(\Carbon\Carbon::parse($s->date_to)->format('d.m.Y')); ?></td>
                    <td><?php echo e($s->opening_balance !== null ? number_format($s->opening_balance, 2, ',', ' ') . ' ₽' : '—'); ?></td>
                    <td><?php echo e($s->closing_balance !== null ? number_format($s->closing_balance, 2, ',', ' ') . ' ₽' : '—'); ?></td>
                    <td><?php if($s->file_name): ?><a href="<?php echo e(route('bank-accounts.statements.download', [$bankAccount, $s])); ?>"><?php echo e($s->file_name); ?></a><?php else: ?> — <?php endif; ?></td>
                    <td><a href="<?php echo e(route('bank-accounts.statements.show', [$bankAccount, $s])); ?>" class="btn btn-sm btn-outline-primary">Подробнее</a></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" class="text-muted">Нет выписок. <a href="<?php echo e(route('bank-accounts.statements.create', $bankAccount)); ?>">Добавить выписку</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php echo e($statements->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/bank-statements/index.blade.php ENDPATH**/ ?>