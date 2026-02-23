<?php $__env->startSection('title', 'Редактировать категорию'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4">Редактировать категорию</h1>
<form method="post" action="<?php echo e(route('item-categories.update', $itemCategory)); ?>">
    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <div class="mb-3"><label class="form-label">Название *</label><input type="text" name="name" class="form-control" value="<?php echo e(old('name', $itemCategory->name)); ?>" required></div>
    <div class="mb-3"><label class="form-label">Родительская категория</label><select name="parent_id" class="form-select"><option value="">—</option><?php $__currentLoopData = $parents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($p->id); ?>" <?php echo e(old('parent_id', $itemCategory->parent_id) == $p->id ? 'selected' : ''); ?>><?php echo e($p->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
    <div class="mb-3">
        <label class="form-label">Доп. подсказка для AI-оценки</label>
        <textarea name="evaluation_config[ai_prompt_suffix]" class="form-control" rows="2" placeholder="Например: учитывать износ, актуальные цены на ювелирку…"><?php echo e(old('evaluation_config.ai_prompt_suffix', $itemCategory->evaluation_config['ai_prompt_suffix'] ?? '')); ?></textarea>
        <small class="text-muted">Добавляется к промпту ИИ при оценке товаров этой категории.</small>
    </div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="<?php echo e(route('item-categories.index')); ?>" class="btn btn-secondary">Отмена</a>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/item-categories/edit.blade.php ENDPATH**/ ?>