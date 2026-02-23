<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Договор скупки <?php echo e($purchaseContract->contract_number); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="p-4">
    <div class="no-print mb-3"><a href="<?php echo e(route('purchase-contracts.show', $purchaseContract)); ?>" class="btn btn-secondary">← Назад</a> <button onclick="window.print()" class="btn btn-primary">Печать</button></div>
    <div class="container">
        <h4 class="text-center mb-4">ДОГОВОР СКУПКИ № <?php echo e($purchaseContract->contract_number); ?></h4>
        <p><strong>Продавец:</strong> <?php echo e($purchaseContract->client->full_name); ?>, тел. <?php echo e($purchaseContract->client->phone); ?></p>
        <p><strong>Товар:</strong> <?php echo e($purchaseContract->item->name); ?> (штрихкод <?php echo e($purchaseContract->item->barcode); ?>)</p>
        <p><strong>Сумма скупки:</strong> <?php echo e(number_format($purchaseContract->purchase_amount, 0, '', ' ')); ?> руб.</p>
        <p><strong>Дата:</strong> <?php echo e($purchaseContract->purchase_date ? \Carbon\Carbon::parse($purchaseContract->purchase_date)->format('d.m.Y') : '—'); ?></p>
        <p><strong>Магазин:</strong> <?php echo e($purchaseContract->store->name); ?>, <?php echo e($purchaseContract->store->address ?? ''); ?></p>
        <p class="mt-4"><strong>Принял:</strong> <?php echo e($purchaseContract->appraiser?->name ?? '—'); ?></p>
    </div>
</body>
</html>
<?php /**PATH /Users/evgeny/pawn-shop-mvp/resources/views/purchase-contracts/print.blade.php ENDPATH**/ ?>