<?php $__env->startSection('title', 'Пользователи'); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Пользователи</h1>
    <a href="<?php echo e(route('users.create')); ?>" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<table class="table table-hover">
    <thead><tr><th>Имя</th><th>Email</th><th>Роль</th><th>Магазин</th><th></th></tr></thead>
    <tbody>
        <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><?php echo e($u->name); ?></td>
            <td><?php echo e($u->email); ?></td>
            <td><span class="badge bg-secondary"><?php echo e($u->role); ?></span></td>
            <td><?php echo e($u->store?->name ?? '—'); ?></td>
            <td>
                <a href="<?php echo e(route('users.edit', $u)); ?>" class="btn btn-sm btn-outline-secondary">Изменить</a>
                <?php if($u->id !== auth()->id()): ?>
                <form action="<?php echo e(route('users.destroy', $u)); ?>" method="post" class="d-inline" onsubmit="return confirm('Удалить пользователя?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button></form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
</table>
<?php echo e($users->links()); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/users/index.blade.php ENDPATH**/ ?>