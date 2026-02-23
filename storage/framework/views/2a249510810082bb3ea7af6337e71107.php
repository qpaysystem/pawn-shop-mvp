<?php $__env->startSection('title', 'Виды расходов'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-list-ul me-2"></i>Виды расходов</h1>
    <a href="<?php echo e(route('expense-types.create')); ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Код</th><th>Название</th><th>Счёт учёта</th><th></th></tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $expenseTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $et): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($et->code ?? '—'); ?></td>
                    <td><?php echo e($et->name); ?></td>
                    <td><?php echo e($et->account ? $et->account->code . ' ' . $et->account->name : '—'); ?></td>
                    <td>
                        <a href="<?php echo e(route('expense-types.edit', $et)); ?>" class="btn btn-sm btn-outline-secondary">Изменить</a>
                        <?php if(!$et->expenses()->exists()): ?>
                        <form action="<?php echo e(route('expense-types.destroy', $et)); ?>" method="post" class="d-inline" onsubmit="return confirm('Удалить вид расхода?')">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="4" class="text-muted">Нет видов расходов. Добавьте первый.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/expense-types/index.blade.php ENDPATH**/ ?>