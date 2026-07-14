@extends('layouts.app')

@section('title', $lead->lead_number)

@section('content')
<div class="mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="{{ route('section.contact-center') }}">Контакт центр</a></li>
            <li class="breadcrumb-item"><a href="{{ route('contact-center.leads.index') }}">Заявки</a></li>
            <li class="breadcrumb-item active">{{ $lead->lead_number }}</li>
        </ol>
    </nav>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h1 class="h4 mb-1">{{ $lead->lead_number }} — {{ $lead->typeLabel() }}</h1>
            <div class="text-muted small">
                {{ $lead->channelLabel() }} · создана {{ is_string($lead->created_at) ? \Carbon\Carbon::parse($lead->created_at)->format('d.m.Y H:i') : $lead->created_at?->format('d.m.Y H:i') }}
                @if($lead->createdByUser) · {{ $lead->createdByUser->name }} @endif
            </div>
        </div>
        @php
            $badge = match($lead->status) {
                'new' => 'text-bg-primary',
                'in_work', 'waiting_client' => 'text-bg-warning',
                'scheduled' => 'text-bg-info',
                'assigned_to_store' => 'text-bg-success',
                'reserved' => 'text-bg-info',
                'converted' => 'text-bg-dark',
                'closed_lost', 'spam' => 'text-bg-secondary',
                default => 'text-bg-light',
            };
        @endphp
        <span class="badge rounded-pill {{ $badge }} fs-6">{{ $lead->statusLabel() }}</span>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger py-2">
        @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">Клиент и контакт</div>
            <div class="card-body">
                @if($lead->client)
                    <div class="fw-semibold">{{ $lead->client->full_name }}</div>
                    <div class="text-muted">{{ $lead->client->phone }}</div>
                @else
                    <div class="fw-semibold">{{ $lead->contact_name ?: '—' }}</div>
                    <div class="text-muted">{{ $lead->contact_phone ?: '—' }}</div>
                @endif
                @if($lead->preferred_at)
                    <div class="mt-2 small"><i class="bi bi-calendar-event"></i> Визит: {{ is_string($lead->preferred_at) ? \Carbon\Carbon::parse($lead->preferred_at)->format('d.m.Y H:i') : $lead->preferred_at->format('d.m.Y H:i') }}</div>
                @endif
                @if($lead->callCenterContact)
                    <div class="mt-2">
                        <a href="{{ route('call-center.show', $lead->callCenterContact) }}" class="small">Связанное обращение</a>
                    </div>
                @endif
            </div>
        </div>

        @if($lead->item)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">Товар</div>
                <div class="card-body">
                    <div class="fw-semibold">{{ $lead->item->name }}</div>
                    <div class="text-muted small">
                        {{ $lead->item->lmb_ref ?: $lead->item->barcode }}
                        @if($lead->item->current_price) · {{ number_format((float)$lead->item->current_price, 0, ',', ' ') }} ₽ @endif
                        @if($lead->item->store) · {{ $lead->item->store->name }} @endif
                    </div>
                    @if($lead->activeReservation)
                        <div class="alert alert-info py-2 mt-2 mb-0 small">
                            <i class="bi bi-bookmark-check"></i>
                            Забронирован до {{ is_string($lead->activeReservation->reserved_until) ? \Carbon\Carbon::parse($lead->activeReservation->reserved_until)->format('d.m.Y H:i') : $lead->activeReservation->reserved_until->format('d.m.Y H:i') }}
                            @if($lead->activeReservation->contact_name || $lead->activeReservation->client)
                                · {{ $lead->activeReservation->client?->full_name ?? $lead->activeReservation->contact_name }}
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if($lead->items->isNotEmpty())
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white">Позиции ({{ $lead->items->count() }})</div>
                <div class="list-group list-group-flush">
                    @foreach($lead->items as $item)
                        <div class="list-group-item">
                            <div class="fw-semibold">{{ $item->title }}</div>
                            @if($item->description)
                                <div class="small text-muted">{{ $item->description }}</div>
                            @endif
                            <div class="small mt-1">
                                @if($item->expected_price)
                                    Ожидает: {{ number_format((float)$item->expected_price, 0, ',', ' ') }} ₽
                                @endif
                                @if($item->appraised_from || $item->appraised_to)
                                    · Оценка:
                                    @if($item->appraised_from) от {{ number_format((float)$item->appraised_from, 0, ',', ' ') }} @endif
                                    @if($item->appraised_to) до {{ number_format((float)$item->appraised_to, 0, ',', ' ') }} @endif ₽
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">История</div>
            <div class="list-group list-group-flush">
                @forelse($lead->events as $event)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span class="fw-medium">{{ $event->typeLabel() }}</span>
                            <span class="text-muted small">{{ is_string($event->created_at) ? \Carbon\Carbon::parse($event->created_at)->format('d.m.Y H:i') : $event->created_at?->format('d.m.Y H:i') }}</span>
                        </div>
                        @if($event->message)
                            <div class="small mt-1">{{ $event->message }}</div>
                        @endif
                        @if($event->createdByUser)
                            <div class="text-muted small">{{ $event->createdByUser->name }}</div>
                        @endif
                    </div>
                @empty
                    <div class="list-group-item text-muted">Событий пока нет</div>
                @endforelse
            </div>
        </div>

        <form method="post" action="{{ route('contact-center.leads.note', $lead) }}" class="card border-0 shadow-sm">
            @csrf
            <div class="card-header bg-white">Добавить заметку</div>
            <div class="card-body">
                <textarea name="message" class="form-control mb-2" rows="2" required placeholder="Комментарий оператора..."></textarea>
                <button type="submit" class="btn btn-sm btn-outline-primary">Сохранить заметку</button>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <form method="post" action="{{ route('contact-center.leads.update', $lead) }}" class="card border-0 shadow-sm mb-3">
            @csrf
            @method('PUT')
            <div class="card-header bg-white">Управление</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Статус</label>
                    <select name="status" class="form-select">
                        @foreach($statuses as $k => $label)
                            <option value="{{ $k }}" @selected($lead->status === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Дата визита</label>
                    <input type="datetime-local" name="preferred_at" class="form-control"
                        value="{{ $lead->preferred_at ? (is_string($lead->preferred_at) ? \Carbon\Carbon::parse($lead->preferred_at)->format('Y-m-d\TH:i') : $lead->preferred_at->format('Y-m-d\TH:i')) : '' }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Заметки</label>
                    <textarea name="notes" class="form-control" rows="4">{{ $lead->notes }}</textarea>
                </div>
                @if(in_array($lead->status, ['closed_lost', 'spam'], true) || request('show_lost'))
                    <div class="mb-3">
                        <label class="form-label">Причина закрытия</label>
                        <select name="lost_reason" class="form-select">
                            <option value="">—</option>
                            @foreach($lostReasons as $k => $label)
                                <option value="{{ $k }}" @selected($lead->lost_reason === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button type="submit" class="btn btn-primary w-100">Сохранить</button>
            </div>
        </form>

        <form method="post" action="{{ route('contact-center.leads.assign', $lead) }}" class="card border-0 shadow-sm mb-3">
            @csrf
            <div class="card-header bg-white">Передать в точку</div>
            <div class="card-body">
                <p class="small text-muted">Оператор не оформляет договор — только передаёт заявку в магазин.</p>
                <select name="store_id_target" class="form-select mb-2" required>
                    <option value="">Выберите точку</option>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" @selected($lead->store_id_target == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-success w-100">Передать в точку</button>
            </div>
        </form>

        @if($lead->item_id)
            @if($lead->activeReservation)
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-white">Бронь</div>
                    <div class="card-body">
                        <p class="small mb-2">До {{ is_string($lead->activeReservation->reserved_until) ? \Carbon\Carbon::parse($lead->activeReservation->reserved_until)->format('d.m.Y H:i') : $lead->activeReservation->reserved_until->format('d.m.Y H:i') }}</p>
                        <form method="post" action="{{ route('contact-center.leads.cancel-reservation', $lead) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger w-100 btn-sm" onclick="return confirm('Снять бронь?')">Снять бронь</button>
                        </form>
                    </div>
                </div>
            @else
                <form method="post" action="{{ route('contact-center.leads.reserve', $lead) }}" class="card border-0 shadow-sm mb-3">
                    @csrf
                    <div class="card-header bg-white">Забронировать товар</div>
                    <div class="card-body">
                        <p class="small text-muted">Бронь на 1–5 дней. Товар будет отмечен в карточке.</p>
                        <label class="form-label">Срок (дней)</label>
                        <select name="days" class="form-select mb-2" required>
                            @foreach($reservationDays as $d)
                                <option value="{{ $d }}" @selected($d === 2)>{{ $d }}</option>
                            @endforeach
                        </select>
                        <label class="form-label">Комментарий</label>
                        <textarea name="notes" class="form-control mb-2" rows="2" placeholder="Необязательно"></textarea>
                        <button type="submit" class="btn btn-primary w-100">Забронировать</button>
                    </div>
                </form>
            @endif
        @endif

        @if($lead->targetStore)
            <div class="card border-0 shadow-sm">
                <div class="card-body small">
                    <div class="text-muted">Текущая точка</div>
                    <div class="fw-semibold">{{ $lead->targetStore->name }}</div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
