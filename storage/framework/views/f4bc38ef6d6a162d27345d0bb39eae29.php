<?php $__env->startSection('title', 'Документ начисления ФОТ'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Документ начисления ФОТ</h1>
    <a href="<?php echo e(route('payroll-accruals.index')); ?>" class="btn btn-outline-secondary">К списку</a>
</div>
<form method="post" action="<?php echo e(route('payroll-accruals.store')); ?>">
    <?php echo csrf_field(); ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Месяц</label>
                    <select name="period_month" class="form-select" required>
                        <?php for($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo e($m); ?>" <?php if($periodMonth == $m): echo 'selected'; endif; ?>><?php echo e($m); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Год</label>
                    <input type="number" name="period_year" class="form-control" value="<?php echo e($periodYear); ?>" min="2020" max="2100" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Дата начисления *</label>
                    <input type="date" name="accrual_date" class="form-control" value="<?php echo e(old('accrual_date', date('Y-m-d'))); ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Примечание</label>
                <textarea name="notes" class="form-control" rows="1"><?php echo e(old('notes')); ?></textarea>
            </div>
            <hr>
            <h6 class="mb-3">Начисления по сотрудникам</h6>
            <table class="table table-sm">
                <thead><tr><th>Сотрудник</th><th>Сумма (₽)</th></tr></thead>
                <tbody>
                    <?php $__currentLoopData = $employees; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $emp): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr>
                        <td><?php echo e($emp->full_name); ?> <?php if($emp->position): ?><small class="text-muted">(<?php echo e($emp->position); ?>)</small><?php endif; ?></td>
                        <td style="width:180px">
                            <input type="hidden" name="items[<?php echo e($loop->index); ?>][employee_id]" value="<?php echo e($emp->id); ?>">
                            <input type="number" name="items[<?php echo e($loop->index); ?>][amount]" class="form-control form-control-sm" step="0.01" min="0" value="0">
                        </td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
            <?php if($employees->isEmpty()): ?>
            <p class="text-muted mb-0">Нет активных сотрудников. <a href="<?php echo e(route('employees.create')); ?>">Добавьте сотрудников</a>.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php if($employees->isNotEmpty()): ?>
    <button type="submit" class="btn btn-primary">Создать документ</button>
    <?php endif; ?>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/payroll-accruals/create.blade.php ENDPATH**/ ?>