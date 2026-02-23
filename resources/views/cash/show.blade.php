@extends('layouts.app')

@section('title', 'Кассовый документ ' . $cashDocument->document_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Кассовый документ {{ $cashDocument->document_number }}</h1>
    <div>
        @if(auth()->user()->canProcessSales())
        <a href="{{ route('cash.edit', $cashDocument) }}" class="btn btn-outline-primary">Изменить</a>
        <form action="{{ route('cash.destroy', $cashDocument) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить документ?')">@csrf @method('DELETE')<button type="submit" class="btn btn-outline-danger">Удалить</button></form>
        @endif
        <a href="{{ route('cash.index', ['store_id' => $cashDocument->store_id]) }}" class="btn btn-outline-secondary">К списку</a>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-document">Документ</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-ledger">Бухгалтерские проводки</a></li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="tab-document">
<div class="card">
    <div class="card-body">
        <p><strong>Дата:</strong> {{ \Carbon\Carbon::parse($cashDocument->document_date)->format('d.m.Y') }}</p>
        <p><strong>Вид операции:</strong> {{ $cashDocument->operationType->name }}</p>
        <p><strong>Магазин:</strong> {{ $cashDocument->store?->name ?? '—' }}</p>
        @if($cashDocument->isTransfer() && $cashDocument->targetStore)
        <p><strong>Касса назначения:</strong> {{ $cashDocument->targetStore->name }}</p>
        @endif
        @if($cashDocument->client)
        <p><strong>Клиент:</strong> <a href="{{ route('clients.show', $cashDocument->client) }}">{{ $cashDocument->client->full_name }}</a></p>
        @endif
        <p><strong>Сумма:</strong> {{ number_format($cashDocument->amount, 2, ',', ' ') }} ₽</p>
        @if($cashDocument->comment)<p><strong>Комментарий:</strong> {{ $cashDocument->comment }}</p>@endif
        @if($cashDocument->createdByUser)<p class="text-muted small mb-0">Создал: {{ $cashDocument->createdByUser->name ?? $cashDocument->createdByUser->email }}, {{ \Carbon\Carbon::parse($cashDocument->created_at)->format('d.m.Y H:i') }}</p>@endif
    </div>
</div>
</div>
<div class="tab-pane fade" id="tab-ledger">
    @include('documents._ledger_tab', [
        'documentType' => 'cash_document',
        'documentId' => $cashDocument->id,
        'ledgerEntries' => $ledgerEntries,
        'templates' => $templates,
    ])
</div>
</div>
@endsection
