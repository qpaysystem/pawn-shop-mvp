<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Договор комиссии {{ $commissionContract->contract_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="p-4">
    <div class="no-print mb-3"><a href="{{ route('commission-contracts.show', $commissionContract) }}" class="btn btn-secondary">← Назад</a> <button onclick="window.print()" class="btn btn-primary">Печать</button></div>
    <div class="container">
        <h4 class="text-center mb-4">ДОГОВОР КОМИССИИ № {{ $commissionContract->contract_number }}</h4>
        <p><strong>Комитент:</strong> {{ $commissionContract->client->full_name }}, тел. {{ $commissionContract->client->phone }}</p>
        <p><strong>Товар:</strong> {{ $commissionContract->item->name }} (штрихкод {{ $commissionContract->item->barcode }})</p>
        <p><strong>Цена продажи:</strong> {{ $commissionContract->seller_price ? number_format($commissionContract->seller_price, 0, '', ' ') : '—' }} руб.</p>
        <p><strong>Комиссия:</strong> {{ $commissionContract->commission_percent ?? '—' }}%, к выплате комитенту: {{ $commissionContract->client_price ? number_format($commissionContract->client_price, 0, '', ' ') : '—' }} руб.</p>
        <p><strong>Срок до:</strong> {{ $commissionContract->expiry_date ? \Carbon\Carbon::parse($commissionContract->expiry_date)->format('d.m.Y') : '—' }}</p>
        <p><strong>Магазин:</strong> {{ $commissionContract->store->name }}, {{ $commissionContract->store->address }}</p>
        <p class="mt-4"><strong>Принял:</strong> {{ $commissionContract->appraiser?->name ?? '—' }}</p>
    </div>
</body>
</html>
