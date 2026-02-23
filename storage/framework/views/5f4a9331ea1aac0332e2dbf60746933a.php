<?php $__env->startSection('title', 'Расчётные счета'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-bank me-2"></i>Расчётные счета</h1>
    <a href="<?php echo e(route('bank-accounts.create')); ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить счёт</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Название</th><th>Банк</th><th>Номер счёта</th><th>Магазин</th><th></th></tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $bankAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ba): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($ba->name); ?></td>
                    <td><?php echo e($ba->bank_name ?? '—'); ?></td>
                    <td><?php echo e($ba->account_number ?? '—'); ?></td>
                    <td><?php echo e($ba->store?->name ?? '—'); ?></td>
                    <td>
                        <a href="<?php echo e(route('bank-accounts.statements.index', $ba)); ?>" class="btn btn-sm btn-outline-primary">Выписки</a>
                        <a href="<?php echo e(route('bank-accounts.edit', $ba)); ?>" class="btn btn-sm btn-outline-secondary">Изменить</a>
                        <?php if(!$ba->bankStatements()->exists()): ?>
                        <form action="<?php echo e(route('bank-accounts.destroy', $ba)); ?>" method="post" class="d-inline" onsubmit="return confirm('Удалить счёт?')">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" class="text-muted">Нет расчётных счетов. Добавьте первый.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/bank-accounts/index.blade.php ENDPATH**/ ?>