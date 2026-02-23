<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Договор залога {{ $pawnContract->contract_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>@media print { .no-print { display: none !important; } }</style>
</head>
<body class="p-4">
    <div class="no-print mb-3"><a href="{{ route('pawn-contracts.show', $pawnContract) }}" class="btn btn-secondary">← Назад</a> <button onclick="window.print()" class="btn btn-primary">Печать</button></div>
    <div class="container">
        <h4 class="text-center mb-4">ДОГОВОР ЗАЛОГА № {{ $pawnContract->contract_number }}</h4>
        <p>Дата: {{ \Carbon\Carbon::parse($pawnContract->loan_date)->format('d.m.Y') }}</p>
        <p><strong>Залогодатель:</strong> {{ $pawnContract->client->full_name }}, тел. {{ $pawnContract->client->phone }}@if($pawnContract->client->passport_data), {{ $pawnContract->client->passport_data }}@endif</p>
        <p><strong>Товар:</strong> {{ $pawnContract->item->name }} (штрихкод {{ $pawnContract->item->barcode }})</p>
        <p><strong>Сумма займа:</strong> {{ number_format($pawnContract->loan_amount, 0, '', ' ') }} ({{ number_format($pawnContract->loan_amount, 0, '', ' ') }}) руб.</p>
        <p><strong>Процент:</strong> {{ $pawnContract->loan_percent ?? 0 }}%</p>
        <p><strong>Сумма выкупа:</strong> {{ number_format($pawnContract->buyback_amount ?? 0, 0, '', ' ') }} руб.</p>
        <p><strong>Срок действия залога до:</strong> {{ \Carbon\Carbon::parse($pawnContract->expiry_date)->format('d.m.Y') }}</p>
        <p><strong>Магазин:</strong> {{ $pawnContract->store->name }}, {{ $pawnContract->store->address }}</p>
        <p class="mt-4"><strong>Принял:</strong> {{ $pawnContract->appraiser?->name ?? '—' }}</p>
    </div>
</body>
</html>
