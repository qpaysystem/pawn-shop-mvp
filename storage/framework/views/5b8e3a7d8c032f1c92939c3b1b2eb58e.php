<?php $__env->startSection('title', 'Новый магазин'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4">Новый магазин</h1>
<form method="post" action="<?php echo e(route('stores.store')); ?>">
    <?php echo csrf_field(); ?>
    <div class="mb-3"><label class="form-label">Название *</label><input type="text" name="name" class="form-control" value="<?php echo e(old('name')); ?>" required></div>
    <div class="mb-3"><label class="form-label">Адрес</label><input type="text" name="address" class="form-control" value="<?php echo e(old('address')); ?>"></div>
    <div class="mb-3"><label class="form-label">Телефон</label><input type="text" name="phone" class="form-control" value="<?php echo e(old('phone')); ?>"></div>
    <div class="mb-3 form-check"><input type="checkbox" name="is_active" class="form-check-input" value="1" <?php echo e(old('is_active', true) ? 'checked' : ''); ?>><label class="form-check-label">Активен</label></div>
    <button type="submit" class="btn btn-primary">Создать</button>
    <a href="<?php echo e(route('stores.index')); ?>" class="btn btn-secondary">Отмена</a>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/stores/create.blade.php ENDPATH**/ ?>