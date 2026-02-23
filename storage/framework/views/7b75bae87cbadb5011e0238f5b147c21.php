<?php $__env->startSection('title', 'Расходы'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-cash-expense me-2"></i>Расходы</h1>
    <div>
        <a href="<?php echo e(route('expense-types.index')); ?>" class="btn btn-outline-secondary me-2">Виды расходов</a>
        <a href="<?php echo e(route('expenses.create')); ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Начислить расход</a>
    </div>
</div>
<form method="get" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="expense_type_id" class="form-select form-select-sm">
            <option value="">Все виды</option>
            <?php $__currentLoopData = $expenseTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $et): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($et->id); ?>" <?php if(request('expense_type_id') == $et->id): echo 'selected'; endif; ?>><?php echo e($et->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="col-auto">
        <select name="store_id" class="form-select form-select-sm">
            <option value="">Все магазины</option>
            <?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($s->id); ?>" <?php if(request('store_id') == $s->id): echo 'selected'; endif; ?>><?php echo e($s->name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
    </div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary">Показать</button></div>
</form>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>№</th><th>Дата</th><th>Вид расхода</th><th>Магазин</th><th>Сумма</th><th></th></tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $expenses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($e->number); ?></td>
                    <td><?php echo e(\Carbon\Carbon::parse($e->expense_date)->format('d.m.Y')); ?></td>
                    <td><?php echo e($e->expenseType->name); ?></td>
                    <td><?php echo e($e->store?->name ?? '—'); ?></td>
                    <td><?php echo e(number_format($e->amount, 2, ',', ' ')); ?> ₽</td>
                    <td><a href="<?php echo e(route('expenses.show', $e)); ?>" class="btn btn-sm btn-outline-primary">Подробнее</a></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="6" class="text-muted">Нет документов. <a href="<?php echo e(route('expenses.create')); ?>">Начислить расход</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php echo e($expenses->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/expenses/index.blade.php ENDPATH**/ ?>