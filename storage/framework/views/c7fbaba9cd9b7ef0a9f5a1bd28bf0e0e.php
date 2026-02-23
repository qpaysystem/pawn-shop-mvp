<?php $__env->startSection('title', 'Добавить расчётный счёт'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Новый расчётный счёт</h1>
    <a href="<?php echo e(route('bank-accounts.index')); ?>" class="btn btn-outline-secondary">К списку</a>
</div>
<form method="post" action="<?php echo e(route('bank-accounts.store')); ?>">
    <?php echo csrf_field(); ?>
    <div class="card mb-3">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Название *</label>
                <input type="text" name="name" class="form-control <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" value="<?php echo e(old('name')); ?>" required>
                <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Банк</label>
                <input type="text" name="bank_name" class="form-control" value="<?php echo e(old('bank_name')); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Номер счёта</label>
                <input type="text" name="account_number" class="form-control" value="<?php echo e(old('account_number')); ?>">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">БИК</label>
                    <input type="text" name="bik" class="form-control" value="<?php echo e(old('bik')); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Корр. счёт</label>
                    <input type="text" name="correspondent_account" class="form-control" value="<?php echo e(old('correspondent_account')); ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Магазин</label>
                <select name="store_id" class="form-select">
                    <option value="">— не указан</option>
                    <?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($s->id); ?>" <?php if(old('store_id') == $s->id): echo 'selected'; endif; ?>><?php echo e($s->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="form-check mb-0">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" <?php if(old('is_active', true)): echo 'checked'; endif; ?>>
                <label class="form-check-label" for="is_active">Активен</label>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Добавить</button>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/bank-accounts/create.blade.php ENDPATH**/ ?>