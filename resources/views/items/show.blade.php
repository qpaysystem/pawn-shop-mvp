@extends('layouts.app')

@section('title', $item->name)

@section('content')
@php $tab = request('tab', 'main'); @endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1">{{ $item->name }}</h1>
        @if($activeReservation)
            <span class="badge text-bg-info"><i class="bi bi-bookmark-check"></i> Забронирован до {{ is_string($activeReservation->reserved_until) ? \Carbon\Carbon::parse($activeReservation->reserved_until)->format('d.m.Y H:i') : $activeReservation->reserved_until->format('d.m.Y H:i') }}</span>
        @endif
    </div>
    <div>
        @if(auth()->user()->canManageStorage())
        <a href="{{ route('items.edit', $item) }}" class="btn btn-outline-primary">Изменить</a>
        @endif
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'main' ? 'active' : '' }}" href="{{ route('items.show', $item) }}">Основное</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $tab === 'life' ? 'active' : '' }}" href="{{ route('items.show', [$item, 'tab' => 'life']) }}">Карта жизни</a>
    </li>
</ul>

@if($tab === 'life')
    @php
        $avitoListing = $avitoSummary['listing'] ?? null;
        $hasAvito = $avitoListing || ($avitoSummary['chats_count'] ?? 0) > 0;
    @endphp
    @if($hasAvito)
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
            <span><i class="bi bi-shop"></i> Avito</span>
            @if($avitoListing && !empty($avitoListing['url']))
                <a href="{{ $avitoListing['url'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark">Открыть объявление</a>
            @endif
        </div>
        <div class="card-body">
            @if($avitoListing)
                <p class="mb-2"><strong>{{ $avitoListing['title'] }}</strong></p>
                <div class="small text-muted mb-3">
                    @if(!empty($avitoListing['price'])){{ $avitoListing['price'] }} · @endif
                    @if(!empty($avitoListing['id']))ID {{ $avitoListing['id'] }} · @endif
                    {{ $avitoListing['status'] ?? 'active' }}
                </div>
            @else
                <p class="text-muted mb-3">Активное объявление в API не найдено, но есть чаты по похожему названию.</p>
            @endif
            <div class="row g-3 text-center">
                <div class="col-6 col-md-3">
                    <div class="fs-4 fw-semibold">{{ $avitoSummary['chats_count'] ?? 0 }}</div>
                    <div class="small text-muted">чатов</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fs-4 fw-semibold">{{ $avitoSummary['inquiries_total'] ?? 0 }}</div>
                    <div class="small text-muted">обращений всего</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fs-4 fw-semibold">{{ $avitoSummary['inquiries_30d'] ?? 0 }}</div>
                    <div class="small text-muted">за 30 дней</div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="fs-6 fw-semibold">
                        @if(!empty($avitoSummary['last_inquiry_at']))
                            {{ $avitoSummary['last_inquiry_at']->format('d.m.Y H:i') }}
                        @else
                            —
                        @endif
                    </div>
                    <div class="small text-muted">последнее обращение</div>
                </div>
            </div>
        </div>
    </div>
    @endif
    <div class="card mb-4">
        <div class="card-header">Хронология</div>
        <div class="card-body">
            @forelse($lifeMap as $event)
                <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                    <div class="text-muted small text-nowrap" style="min-width:110px;">{{ is_string($event['at']) ? \Carbon\Carbon::parse($event['at'])->format('d.m.Y H:i') : $event['at']->format('d.m.Y H:i') }}</div>
                    <div>
                        @php
                            $icon = match($event['kind']) {
                                'status' => 'bi-arrow-left-right text-secondary',
                                'move' => 'bi-truck text-primary',
                                'reservation' => 'bi-bookmark-check text-info',
                                'lead' => 'bi-inbox text-primary',
                                'contract' => 'bi-file-text text-success',
                                'lmb_event' => 'bi-activity text-secondary',
                                'avito_listing' => 'bi-shop text-warning',
                                'avito_contact' => 'bi-chat-dots text-warning',
                                default => 'bi-circle text-muted',
                            };
                        @endphp
                        <div>
                            <i class="bi {{ $icon }}"></i>
                            @if(!empty($event['url']) && ($event['kind'] !== 'avito_contact' || auth()->user()->canAccessContactCenter()))
                                <a href="{{ $event['url'] }}" @if(str_starts_with($event['url'], 'http')) target="_blank" rel="noopener" @endif>{{ $event['title'] }}</a>
                            @else
                                {{ $event['title'] }}
                            @endif
                        </div>
                        @if(!empty($event['meta']))
                            <div class="small text-muted">{{ $event['meta'] }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">Событий пока нет.</p>
            @endforelse
        </div>
    </div>
@else
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
        @if($activeReservation)
        <div class="card mb-4 border-info">
            <div class="card-header bg-info-subtle">Активная бронь</div>
            <div class="card-body">
                <p class="mb-1"><strong>До:</strong> {{ is_string($activeReservation->reserved_until) ? \Carbon\Carbon::parse($activeReservation->reserved_until)->format('d.m.Y H:i') : $activeReservation->reserved_until->format('d.m.Y H:i') }}</p>
                <p class="mb-1"><strong>Клиент:</strong> {{ $activeReservation->client?->full_name ?? $activeReservation->contact_name ?? '—' }}</p>
                <p class="mb-1"><strong>Телефон:</strong> {{ $activeReservation->client?->phone ?? $activeReservation->contact_phone ?? '—' }}</p>
                @if($activeReservation->lead && auth()->user()->canAccessContactCenter())
                    <a href="{{ route('contact-center.leads.show', $activeReservation->lead) }}" class="btn btn-sm btn-outline-primary mt-2">Заявка {{ $activeReservation->lead->lead_number }}</a>
                @elseif($activeReservation->lead)
                    <div class="small text-muted mt-2">Заявка {{ $activeReservation->lead->lead_number }}</div>
                @endif
            </div>
        </div>
        @endif
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
@endif
<a href="{{ route('items.index') }}" class="btn btn-secondary">К списку товаров</a>
@endsection
