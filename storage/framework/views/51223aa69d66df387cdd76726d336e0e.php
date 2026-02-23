<?php $__env->startSection('title', 'Сотрудники (ФОТ)'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-person-badge me-2"></i>Сотрудники</h1>
    <div>
        <a href="<?php echo e(route('payroll-accruals.index')); ?>" class="btn btn-outline-primary me-2">Начисления ФОТ</a>
        <a href="<?php echo e(route('employees.create')); ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>ФИО</th><th>Должность</th><th>Магазин</th><th></th></tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($emp->full_name); ?></td>
                    <td><?php echo e($emp->position ?? '—'); ?></td>
                    <td><?php echo e($emp->store?->name ?? '—'); ?></td>
                    <td>
                        <a href="<?php echo e(route('employees.edit', $emp)); ?>" class="btn btn-sm btn-outline-secondary">Изменить</a>
                        <form action="<?php echo e(route('employees.destroy', $emp)); ?>" method="post" class="d-inline" onsubmit="return confirm('Удалить сотрудника?')">
                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="4" class="text-muted">Нет сотрудников. Добавьте для начисления ФОТ.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/employees/index.blade.php ENDPATH**/ ?>