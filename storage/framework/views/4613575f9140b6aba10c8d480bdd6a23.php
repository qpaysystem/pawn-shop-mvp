<?php $__env->startSection('title', 'Отчёт по кассам'); ?>

<?php $__env->startSection('content'); ?>
<h1 class="h4 mb-4">Отчёт по кассам</h1>

<div class="card mb-4">
    <div class="card-body">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Магазин / Касса</th>
                    <th class="text-end">Баланс</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $stores; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($s->name); ?></td>
                    <td class="text-end <?php echo e(($totals[$s->id] ?? 0) >= 0 ? 'text-success' : 'text-danger'); ?>">
                        <?php echo e(number_format($totals[$s->id] ?? 0, 0, ',', ' ')); ?> ₽
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th>Итого по всем кассам</th>
                    <th class="text-end <?php echo e($grandTotal >= 0 ? 'text-success' : 'text-danger'); ?>">
                        <?php echo e(number_format($grandTotal, 0, ',', ' ')); ?> ₽
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<a href="<?php echo e(route('cash.index')); ?>" class="btn btn-outline-primary">← К списку операций</a>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/cash/report.blade.php ENDPATH**/ ?>