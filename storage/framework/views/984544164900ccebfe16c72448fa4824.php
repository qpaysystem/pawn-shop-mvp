<?php $__env->startSection('title', 'Расход ' . $expense->number); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Расход <?php echo e($expense->number); ?></h1>
    <a href="<?php echo e(route('expenses.index')); ?>" class="btn btn-outline-secondary">К списку</a>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-document">Документ</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-ledger">Бухгалтерские проводки</a></li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="tab-document">
<div class="card">
    <div class="card-body">
        <p><strong>Дата:</strong> <?php echo e(\Carbon\Carbon::parse($expense->expense_date)->format('d.m.Y')); ?></p>
        <p><strong>Вид расхода:</strong> <?php echo e($expense->expenseType->name); ?></p>
        <p><strong>Магазин:</strong> <?php echo e($expense->store?->name ?? '—'); ?></p>
        <p><strong>Сумма:</strong> <?php echo e(number_format($expense->amount, 2, ',', ' ')); ?> ₽</p>
        <?php if($expense->description): ?><p><strong>Комментарий:</strong> <?php echo e($expense->description); ?></p><?php endif; ?>
        <?php if($expense->createdByUser): ?><p class="text-muted small mb-0">Создал: <?php echo e($expense->createdByUser->name ?? $expense->createdByUser->email); ?>, <?php echo e(\Carbon\Carbon::parse($expense->created_at)->format('d.m.Y H:i')); ?></p><?php endif; ?>
    </div>
</div>
</div>
<div class="tab-pane fade" id="tab-ledger">
    <?php echo $__env->make('documents._ledger_tab', [
        'documentType' => $documentType,
        'documentId' => $documentId,
        'ledgerEntries' => $ledgerEntries,
        'templates' => $templates,
    ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/expenses/show.blade.php ENDPATH**/ ?>