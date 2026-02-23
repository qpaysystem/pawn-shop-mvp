@extends('layouts.app')

@section('title', $item->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">{{ $item->name }}</h1>
    <div>
        @if(auth()->user()->canManageStorage())
        <a href="{{ route('items.edit', $item) }}" class="btn btn-outline-primary">Изменить</a>
        @endif
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-body">
                <p><strong>Штрихкод:</strong> <code>{{ $item->barcode }}</code></p>
                <p><strong>Магазин:</strong> {{ $item->store->name }}</p>
                <p><strong>Статус:</strong> @if($item->status)<span class="badge" @if($item->status->color) style="background-color:{{ $item->status->color }}" @endif>{{ $item->status->name }}</span>@else—@endif</p>
                <p><strong>Место хранения:</strong> {{ $item->storageLocation?->name ?? '—' }}</p>
                <p><strong>Категория:</strong> {{ $item->category?->name ?? '—' }}</p>
                <p><strong>Бренд:</strong> {{ $item->brand?->name ?? '—' }}</p>
                <p><strong>Оценочная стоимость:</strong> {{ $item->initial_price ? number_format($item->initial_price, 0, '', ' ') . ' ₽' : '—' }}</p>
                <p><strong>Текущая цена:</strong> {{ $item->current_price ? number_format($item->current_price, 0, '', ' ') . ' ₽' : '—' }}</p>
                @if($item->description)<p><strong>Описание:</strong><br>{{ $item->description }}</p>@endif
            </div>
        </div>
        @if($item->photos && count($item->photos) > 0)
        <div class="card mb-4">
            <div class="card-header">Фото</div>
            <div class="card-body d-flex flex-wrap gap-2">
                @foreach($item->photos as $path)
                <a href="{{ asset('storage/' . $path) }}" target="_blank"><img src="{{ asset('storage/' . $path) }}" alt="" style="max-height:120px; max-width:120px; object-fit:cover;" class="rounded"></a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    <div class="col-md-6">
        @if($item->pawnContract)
        <div class="card mb-4">
            <div class="card-header">Договор залога</div>
            <div class="card-body">
                <p><strong>№:</strong> {{ $item->pawnContract->contract_number }}</p>
                <p><strong>Клиент:</strong> <a href="{{ route('clients.show', $item->pawnContract->client) }}">{{ $item->pawnContract->client->full_name }}</a></p>
                <p><strong>Сумма займа:</strong> {{ number_format($item->pawnContract->loan_amount, 0, '', ' ') }} ₽</p>
                <p><strong>Выкуп:</strong> {{ number_format($item->pawnContract->buyback_amount ?? 0, 0, '', ' ') }} ₽</p>
                <p><strong>Срок до:</strong> {{ \Carbon\Carbon::parse($item->pawnContract->expiry_date)->format('d.m.Y') }}</p>
                <p>@if($item->pawnContract->is_redeemed)<span class="badge bg-success">Выкуплен</span>@else<span class="badge bg-warning">Активен</span>@endif</p>
                <a href="{{ route('pawn-contracts.show', $item->pawnContract) }}" class="btn btn-sm btn-outline-primary">Подробнее</a>
                <a href="{{ route('pawn-contracts.print', $item->pawnContract) }}" class="btn btn-sm btn-outline-secondary" target="_blank">Печать</a>
            </div>
        </div>
        @endif
        @if($item->commissionContract)
        <div class="card mb-4">
            <div class="card-header">Договор комиссии</div>
            <div class="card-body">
                <p><strong>№:</strong> {{ $item->commissionContract->contract_number }}</p>
                <p><strong>Клиент (комитент):</strong> <a href="{{ route('clients.show', $item->commissionContract->client) }}">{{ $item->commissionContract->client->full_name }}</a></p>
                <p><strong>Цена продажи:</strong> {{ $item->commissionContract->seller_price ? number_format($item->commissionContract->seller_price, 0, '', ' ') . ' ₽' : '—' }}</p>
                <p>@if($item->commissionContract->is_sold)<span class="badge bg-success">Продан</span>@else<span class="badge bg-warning">Не продан</span>@endif</p>
                <a href="{{ route('commission-contracts.show', $item->commissionContract) }}" class="btn btn-sm btn-outline-primary">Подробнее</a>
                <a href="{{ route('commission-contracts.print', $item->commissionContract) }}" class="btn btn-sm btn-outline-secondary" target="_blank">Печать</a>
            </div>
        </div>
        @endif
        @if($item->purchaseContract)
        <div class="card mb-4">
            <div class="card-header">Договор скупки</div>
            <div class="card-body">
                <p><strong>№:</strong> {{ $item->purchaseContract->contract_number }}</p>
                <p><strong>Продавец:</strong> <a href="{{ route('clients.show', $item->purchaseContract->client) }}">{{ $item->purchaseContract->client->full_name }}</a></p>
                <p><strong>Сумма скупки:</strong> {{ number_format($item->purchaseContract->purchase_amount, 0, '', ' ') }} ₽</p>
                <p><strong>Дата:</strong> {{ \Carbon\Carbon::parse($item->purchaseContract->purchase_date)->format('d.m.Y') }}</p>
                <a href="{{ route('purchase-contracts.show', $item->purchaseContract) }}" class="btn btn-sm btn-outline-primary">Подробнее</a>
                <a href="{{ route('purchase-contracts.print', $item->purchaseContract) }}" class="btn btn-sm btn-outline-secondary" target="_blank">Печать</a>
            </div>
        </div>
        @endif
        <div class="card mb-4">
            <div class="card-header">История статусов</div>
            <div class="card-body">
                @forelse($item->statusHistory as $h)
                <div class="small text-muted">
                    {{ $h->created_at ? \Carbon\Carbon::parse($h->created_at)->format('d.m.Y H:i') : '—' }}: {{ $h->oldStatus?->name ?? '—' }} → {{ $h->newStatus?->name ?? '—' }}
                    @if($h->changedByUser) ({{ $h->changedByUser->name }}) @endif
                </div>
                @empty
                <p class="text-muted mb-0">Нет записей.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
<a href="{{ route('items.index') }}" class="btn btn-secondary">К списку товаров</a>
@endsection
