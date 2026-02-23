<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Договор залога <?php echo e($pawnContract->contract_number); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="p-4">
    <div class="no-print mb-3"><a href="<?php echo e(route('pawn-contracts.show', $pawnContract)); ?>" class="btn btn-secondary">← Назад</a> <button onclick="window.print()" class="btn btn-primary">Печать</button></div>
    <div class="container">
        <h4 class="text-center mb-4">ДОГОВОР ЗАЛОГА № <?php echo e($pawnContract->contract_number); ?></h4>
        <p>Дата: <?php echo e(\Carbon\Carbon::parse($pawnContract->loan_date)->format('d.m.Y')); ?></p>
        <p><strong>Залогодатель:</strong> <?php echo e($pawnContract->client->full_name); ?>, тел. <?php echo e($pawnContract->client->phone); ?><?php if($pawnContract->client->passport_data): ?>, <?php echo e($pawnContract->client->passport_data); ?><?php endif; ?></p>
        <p><strong>Товар:</strong> <?php echo e($pawnContract->item->name); ?> (штрихкод <?php echo e($pawnContract->item->barcode); ?>)</p>
        <p><strong>Сумма займа:</strong> <?php echo e(number_format($pawnContract->loan_amount, 0, '', ' ')); ?> (<?php echo e(number_format($pawnContract->loan_amount, 0, '', ' ')); ?>) руб.</p>
        <p><strong>Процент:</strong> <?php echo e($pawnContract->loan_percent ?? 0); ?>%</p>
        <p><strong>Сумма выкупа:</strong> <?php echo e(number_format($pawnContract->buyback_amount ?? 0, 0, '', ' ')); ?> руб.</p>
        <p><strong>Срок действия залога до:</strong> <?php echo e(\Carbon\Carbon::parse($pawnContract->expiry_date)->format('d.m.Y')); ?></p>
        <p><strong>Магазин:</strong> <?php echo e($pawnContract->store->name); ?>, <?php echo e($pawnContract->store->address); ?></p>
        <p class="mt-4"><strong>Принял:</strong> <?php echo e($pawnContract->appraiser?->name ?? '—'); ?></p>
    </div>
</body>
</html>
<?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/pawn-contracts/print.blade.php ENDPATH**/ ?>