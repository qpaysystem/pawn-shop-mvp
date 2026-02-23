<?php $__env->startSection('title', 'Новый приход / расход'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4">Новый кассовый документ</h1>

<form method="post" action="<?php echo e(route('cash.store')); ?>">
    <?php echo csrf_field(); ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Магазин *</label>
                    <select name="store_id" class="form-select" required>
                        <?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($s->id); ?>" <?php echo e((old('store_id', $storeId) == $s->id) ? 'selected' : ''); ?>><?php echo e($s->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Дата *</label>
                    <input type="date" name="document_date" class="form-control" value="<?php echo e(old('document_date', date('Y-m-d'))); ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Клиент</label>
                    <select name="client_id" class="form-select">
                        <option value="">— Без привязки к клиенту</option>
                        <?php $__currentLoopData = \App\Models\Client::orderBy('full_name')->get(['id','full_name']); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($c->id); ?>" <?php echo e(old('client_id') == $c->id ? 'selected' : ''); ?>><?php echo e($c->full_name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Вид операции *</label>
                    <select name="operation_type_id" id="operation_type_id" class="form-select" required>
                        <optgroup label="Приход">
                            <?php $__currentLoopData = $incomeTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($t->id); ?>" <?php echo e(old('operation_type_id') == $t->id ? 'selected' : ''); ?>><?php echo e($t->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </optgroup>
                        <optgroup label="Расход">
                            <?php $__currentLoopData = $expenseTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($t->id); ?>" <?php echo e(old('operation_type_id') == $t->id ? 'selected' : ''); ?>><?php echo e($t->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </optgroup>
                    </select>
                </div>
                <div class="col-12" id="target_store_wrap" style="<?php echo e((old('operation_type_id') == $transferType?->id) ? '' : 'display:none'); ?>">
                    <label class="form-label">Касса назначения *</label>
                    <select name="target_store_id" id="target_store_id" class="form-select">
                        <option value="">— Выберите кассу</option>
                        <?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($s->id); ?>" <?php echo e(old('target_store_id') == $s->id ? 'selected' : ''); ?>><?php echo e($s->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <small class="text-muted">Деньги списываются из выбранной выше кассы и зачисляются в кассу назначения.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Сумма (₽) *</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" value="<?php echo e(old('amount')); ?>" placeholder="0.00" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Комментарий</label>
                    <textarea name="comment" class="form-control" rows="2" placeholder="Основание, примечание"><?php echo e(old('comment')); ?></textarea>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Создать документ</button>
    <a href="<?php echo e(route('cash.index')); ?>" class="btn btn-secondary">Отмена</a>
</form>
<?php $__env->startPush('scripts'); ?>
<script>
(function() {
    var opSelect = document.getElementById('operation_type_id');
    var targetWrap = document.getElementById('target_store_wrap');
    var targetSelect = document.getElementById('target_store_id');
    var transferTypeId = <?php echo e($transferType?->id ?? 0); ?>;
    function toggle() {
        var isTransfer = opSelect.value == transferTypeId;
        targetWrap.style.display = isTransfer ? 'block' : 'none';
        targetSelect.required = isTransfer;
    }
    opSelect.addEventListener('change', toggle);
    toggle();
})();
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/cash/create.blade.php ENDPATH**/ ?>