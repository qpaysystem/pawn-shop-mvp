<?php $__env->startSection('title', 'Добавить вид расхода'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Новый вид расхода</h1>
    <a href="<?php echo e(route('expense-types.index')); ?>" class="btn btn-outline-secondary">К списку</a>
</div>
<form method="post" action="<?php echo e(route('expense-types.store')); ?>">
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
                <label class="form-label">Код</label>
                <input type="text" name="code" class="form-control" value="<?php echo e(old('code')); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Счёт учёта (план счетов)</label>
                <select name="account_id" class="form-select">
                    <option value="">— не указан</option>
                    <?php $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($a->id); ?>" <?php if(old('account_id') == $a->id): echo 'selected'; endif; ?>><?php echo e($a->code); ?> <?php echo e($a->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div class="mb-0">
                <label class="form-label">Порядок сортировки</label>
                <input type="number" name="sort_order" class="form-control" value="<?php echo e(old('sort_order', 0)); ?>" min="0">
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Создать</button>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/expense-types/create.blade.php ENDPATH**/ ?>