<?php $__env->startSection('title', 'Начисления ФОТ'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-currency-dollar me-2"></i>Начисления ФОТ</h1>
    <div>
        <a href="<?php echo e(route('employees.index')); ?>" class="btn btn-outline-secondary me-2">Сотрудники</a>
        <a href="<?php echo e(route('payroll-accruals.create')); ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Документ начисления</a>
    </div>
</div>
<p class="text-muted small">Учёт: начисления сотрудникам отображаются на счёте <strong>70</strong> «Расчёты с персоналом по оплате труда».</p>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>№</th><th>Период</th><th>Дата начисления</th><th>Сумма</th><th></th></tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $accruals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($a->number); ?></td>
                    <td><?php echo e($a->period_label); ?></td>
                    <td><?php echo e(\Carbon\Carbon::parse($a->accrual_date)->format('d.m.Y')); ?></td>
                    <td><?php echo e(number_format($a->total_amount, 2, ',', ' ')); ?> ₽</td>
                    <td><a href="<?php echo e(route('payroll-accruals.show', $a)); ?>" class="btn btn-sm btn-outline-primary">Подробнее</a></td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" class="text-muted">Нет начислений. <a href="<?php echo e(route('payroll-accruals.create')); ?>">Создать документ</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php echo e($accruals->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/payroll-accruals/index.blade.php ENDPATH**/ ?>