@extends('layouts.app')

@section('title', 'Договор скупки ' . $purchaseContract->contract_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Договор скупки {{ $purchaseContract->contract_number }}</h1>
    <div>
        <a href="{{ route('purchase-contracts.print', $purchaseContract) }}" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-printer"></i> Печать</a>
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
            <div class="card-header">Продавец (клиент)</div>
            <div class="card-body">
                <p><a href="{{ route('clients.show', $purchaseContract->client) }}">{{ $purchaseContract->client->full_name }}</a></p>
                <p>Телефон: {{ $purchaseContract->client->phone }}</p>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">Условия скупки</div>
            <div class="card-body">
                <p><strong>Сумма скупки:</strong> {{ number_format($purchaseContract->purchase_amount, 0, '', ' ') }} ₽</p>
                <p><strong>Дата:</strong> {{ $purchaseContract->purchase_date ? \Carbon\Carbon::parse($purchaseContract->purchase_date)->format('d.m.Y') : '—' }}</p>
                <p><strong>Принял:</strong> {{ $purchaseContract->appraiser?->name ?? '—' }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Товар</div>
            <div class="card-body">
                <p><a href="{{ route('items.show', $purchaseContract->item) }}">{{ $purchaseContract->item->name }}</a></p>
                <p>Штрихкод: <code>{{ $purchaseContract->item->barcode }}</code></p>
                <p>Магазин: {{ $purchaseContract->store->name }}</p>
            </div>
        </div>
    </div>
</div>
<a href="{{ route('purchase-contracts.index') }}" class="btn btn-secondary">К списку договоров</a>
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
