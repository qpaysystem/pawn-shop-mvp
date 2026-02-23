@extends('layouts.app')

@section('title', 'Договор ' . $commissionContract->contract_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Договор комиссии {{ $commissionContract->contract_number }}</h1>
    <div>
        <a href="{{ route('commission-contracts.print', $commissionContract) }}" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-printer"></i> Печать</a>
        @if(!$commissionContract->is_sold && auth()->user()->canProcessSales())
        <form action="{{ route('commission-contracts.sold', $commissionContract) }}" method="post" class="d-inline" onsubmit="return confirm('Оформить продажу?')">@csrf<button type="submit" class="btn btn-success">Оформить продажу</button></form>
        @endif
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-document">Документ</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-ledger">Бухгалтерские проводки</a></li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="tab-document">
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Клиент (комитент)</div>
            <div class="card-body">
                <p><a href="{{ route('clients.show', $commissionContract->client) }}">{{ $commissionContract->client->full_name }}</a></p>
                <p>Телефон: {{ $commissionContract->client->phone }}</p>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">Условия</div>
            <div class="card-body">
                <p><strong>Комиссия %:</strong> {{ $commissionContract->commission_percent ?? '—' }}</p>
                <p><strong>Сумма комиссии:</strong> {{ $commissionContract->commission_amount ? number_format($commissionContract->commission_amount, 0, '', ' ') . ' ₽' : '—' }}</p>
                <p><strong>Цена продажи:</strong> {{ $commissionContract->seller_price ? number_format($commissionContract->seller_price, 0, '', ' ') . ' ₽' : '—' }}</p>
                <p><strong>Клиенту к выплате:</strong> {{ $commissionContract->client_price ? number_format($commissionContract->client_price, 0, '', ' ') . ' ₽' : '—' }}</p>
                <p><strong>Срок до:</strong> {{ $commissionContract->expiry_date ? \Carbon\Carbon::parse($commissionContract->expiry_date)->format('d.m.Y') : '—' }}</p>
                <p><strong>Принял:</strong> {{ $commissionContract->appraiser?->name ?? '—' }}</p>
                @if($commissionContract->is_sold)
                <p><strong>Продан:</strong> {{ $commissionContract->sold_at ? \Carbon\Carbon::parse($commissionContract->sold_at)->format('d.m.Y H:i') : '—' }}</p>
                <p><strong>Оформил:</strong> {{ $commissionContract->soldByUser?->name ?? '—' }}</p>
                <p><strong>Клиенту перечислено:</strong> @if($commissionContract->client_paid) Да @else Нет @endif</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Товар</div>
            <div class="card-body">
                <p><a href="{{ route('items.show', $commissionContract->item) }}">{{ $commissionContract->item->name }}</a></p>
                <p>Штрихкод: <code>{{ $commissionContract->item->barcode }}</code></p>
                <p>Магазин: {{ $commissionContract->store->name }}</p>
            </div>
        </div>
    </div>
</div>
<a href="{{ route('commission-contracts.index') }}" class="btn btn-secondary">К списку договоров</a>
</div>
<div class="tab-pane fade" id="tab-ledger">
    @include('documents._ledger_tab', [
        'documentType' => $documentType,
        'documentId' => $documentId,
        'ledgerEntries' => $ledgerEntries,
        'templates' => $templates,
    ])
</div>
</div>
@endsection
