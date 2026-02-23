<?php $__env->startSection('title', 'Начисление ФОТ ' . $payrollAccrual->number); ?>

<?php $__env->startSection('content'); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Начисление ФОТ <?php echo e($payrollAccrual->number); ?></h1>
    <a href="<?php echo e(route('payroll-accruals.index')); ?>" class="btn btn-outline-secondary">К списку</a>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-document">Документ</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-ledger">Бухгалтерские проводки</a></li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="tab-document">
<div class="card mb-3">
    <div class="card-body">
        <p><strong>Период:</strong> <?php echo e($payrollAccrual->period_label); ?></p>
        <p><strong>Дата начисления:</strong> <?php echo e(\Carbon\Carbon::parse($payrollAccrual->accrual_date)->format('d.m.Y')); ?></p>
        <p><strong>Итого:</strong> <?php echo e(number_format($payrollAccrual->total_amount, 2, ',', ' ')); ?> ₽</p>
        <p class="text-muted small mb-0"><strong>Учёт:</strong> начисления отображаются на счёте <strong>70</strong> «Расчёты с персоналом по оплате труда».</p>
        <?php if($payrollAccrual->notes): ?><p><strong>Примечание:</strong> <?php echo e($payrollAccrual->notes); ?></p><?php endif; ?>
    </div>
</div>
<div class="card">
    <div class="card-header">По сотрудникам</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Сотрудник</th><th>Сумма</th></tr></thead>
            <tbody>
                <?php $__currentLoopData = $payrollAccrual->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><?php echo e($item->employee->full_name); ?></td>
                    <td><?php echo e(number_format($item->amount, 2, ',', ' ')); ?> ₽</td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
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

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/payroll-accruals/show.blade.php ENDPATH**/ ?>