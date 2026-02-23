<?php $__env->startSection('title', 'Новая выписка'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Добавить выписку: <?php echo e($bankAccount->name); ?></h1>
    <a href="<?php echo e(route('bank-accounts.statements.index', $bankAccount)); ?>" class="btn btn-outline-secondary">К выпискам</a>
</div>
<form method="post" action="<?php echo e(route('bank-accounts.statements.store', $bankAccount)); ?>" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Дата с *</label>
                    <input type="date" name="date_from" class="form-control <?php $__errorArgs = ['date_from'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('date_from')); ?>" required>
                    <?php $__errorArgs = ['date_from'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Дата по *</label>
                    <input type="date" name="date_to" class="form-control <?php $__errorArgs = ['date_to'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('date_to')); ?>" required>
                    <?php $__errorArgs = ['date_to'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Начальное сальдо (₽)</label>
                    <input type="number" name="opening_balance" class="form-control" step="0.01" value="<?php echo e(old('opening_balance')); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Конечное сальдо (₽)</label>
                    <input type="number" name="closing_balance" class="form-control" step="0.01" value="<?php echo e(old('closing_balance')); ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Файл выписки (PDF, CSV, Excel)</label>
                <input type="file" name="file" class="form-control" accept=".pdf,.csv,.txt,.xlsx,.xls">
            </div>
            <div class="mb-0">
                <label class="form-label">Примечание</label>
                <textarea name="notes" class="form-control" rows="2"><?php echo e(old('notes')); ?></textarea>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Создать</button>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/bank-statements/create.blade.php ENDPATH**/ ?>