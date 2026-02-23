@extends('layouts.app')

@section('title', 'Договор ' . $pawnContract->contract_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Договор залога {{ $pawnContract->contract_number }}</h1>
    <div>
        <a href="{{ route('pawn-contracts.print', $pawnContract) }}" class="btn btn-outline-secondary" target="_blank"><i class="bi bi-printer"></i> Печать</a>
        @if(!$pawnContract->is_redeemed && auth()->user()->canProcessSales())
        <form action="{{ route('pawn-contracts.redeem', $pawnContract) }}" method="post" class="d-inline" onsubmit="return confirm('Оформить выкуп?')">@csrf<button type="submit" class="btn btn-success">Оформить выкуп</button></form>
        @endif
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#tab-document">Документ</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tab-ledger">Бухгалтерские проводки</a>
    </li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="tab-document">
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Клиент</div>
            <div class="card-body">
                <p><a href="{{ route('clients.show', $pawnContract->client) }}">{{ $pawnContract->client->full_name }}</a></p>
                <p>Телефон: {{ $pawnContract->client->phone }}</p>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">Условия</div>
            <div class="card-body">
                <p><strong>Сумма займа:</strong> {{ number_format($pawnContract->loan_amount, 0, '', ' ') }} ₽</p>
                <p><strong>Процент:</strong> {{ $pawnContract->loan_percent ?? 0 }}%</p>
                <p><strong>Сумма выкупа:</strong> {{ number_format($pawnContract->buyback_amount ?? 0, 0, '', ' ') }} ₽</p>
                <p><strong>Дата займа:</strong> {{ \Carbon\Carbon::parse($pawnContract->loan_date)->format('d.m.Y') }}</p>
                <p><strong>Срок до:</strong> {{ \Carbon\Carbon::parse($pawnContract->expiry_date)->format('d.m.Y') }}</p>
                <p><strong>Принял:</strong> {{ $pawnContract->appraiser?->name ?? '—' }}</p>
                @if($pawnContract->is_redeemed)
                <p><strong>Выкуплен:</strong> {{ $pawnContract->redeemed_at ? \Carbon\Carbon::parse($pawnContract->redeemed_at)->format('d.m.Y H:i') : '—' }}</p>
                <p><strong>Оформил:</strong> {{ $pawnContract->redeemedByUser?->name ?? '—' }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Товар</div>
            <div class="card-body">
                <p><a href="{{ route('items.show', $pawnContract->item) }}">{{ $pawnContract->item->name }}</a></p>
                <p>Штрихкод: <code>{{ $pawnContract->item->barcode }}</code></p>
                <p>Магазин: {{ $pawnContract->store->name }}</p>
            </div>
        </div>
    </div>
</div>
<a href="{{ route('pawn-contracts.index') }}" class="btn btn-secondary">К списку договоров</a>
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
