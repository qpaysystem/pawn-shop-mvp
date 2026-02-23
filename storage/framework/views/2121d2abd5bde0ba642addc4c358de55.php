<?php $__env->startSection('title', 'Клиенты'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Клиенты</h1>
    <a href="<?php echo e(route('clients.create')); ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<form method="get" class="mb-3 row g-2">
    <div class="col-auto"><input type="text" name="search" class="form-control form-control-sm" placeholder="ФИО, телефон, email" value="<?php echo e(request('search')); ?>"></div>
    <div class="col-auto"><select name="blacklist" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Все</option><option value="1" <?php echo e(request('blacklist') === '1' ? 'selected' : ''); ?>>В чёрном списке</option></select></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-secondary">Найти</button></div>
</form>
<table class="table table-hover">
    <thead><tr><th>ФИО</th><th>Телефон</th><th>Email</th><th>Чёрный список</th><th></th></tr></thead>
    <tbody>
        <?php $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><a href="<?php echo e(route('clients.show', $c)); ?>"><?php echo e($c->full_name); ?></a></td>
            <td><?php echo e($c->phone); ?></td>
            <td><?php echo e($c->email); ?></td>
            <td><?php if($c->blacklist_flag): ?><span class="badge bg-danger">Да</span><?php else: ?>—<?php endif; ?></td>
            <td>
                <a href="<?php echo e(route('clients.edit', $c)); ?>" class="btn btn-sm btn-outline-secondary">Изменить</a>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table>
<?php echo e($clients->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/clients/index.blade.php ENDPATH**/ ?>