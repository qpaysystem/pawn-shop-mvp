@extends('layouts.app')

@section('title', 'Заявки')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('section.contact-center') }}">Контакт центр</a></li>
                <li class="breadcrumb-item active">Заявки</li>
            </ol>
        </nav>
        <h1 class="h4 mb-0">Заявки и черновики</h1>
        <p class="text-muted small mb-0">Очередь обращений: оценка, залог, скупка, продажа.</p>
    </div>
    <a href="{{ route('contact-center.leads.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Новая заявка
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Поиск</label>
                <input type="text" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="Номер, имя, телефон...">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Тип</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">Все</option>
                    @foreach($types as $k => $label)
                        <option value="{{ $k }}" @selected(request('type') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Канал</label>
                <select name="channel" class="form-select form-select-sm">
                    <option value="">Все</option>
                    @foreach($channels as $k => $label)
                        <option value="{{ $k }}" @selected(request('channel') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Статус</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Активные</option>
                    @foreach($statuses as $k => $label)
                        <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-outline-primary me-1">Фильтр</button>
                <a href="{{ route('contact-center.leads.index') }}" class="btn btn-sm btn-outline-secondary">Сброс</a>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Номер</th>
                    <th>Тип</th>
                    <th>Канал</th>
                    <th>Клиент</th>
                    <th>Статус</th>
                    <th>Точка</th>
                    <th>Создана</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($leads as $lead)
                    <tr>
                        <td class="fw-semibold">{{ $lead->lead_number }}</td>
                        <td>{{ $lead->typeLabel() }}</td>
                        <td>{{ $lead->channelLabel() }}</td>
                        <td>
                            @if($lead->client)
                                {{ $lead->client->full_name }}<br>
                                <span class="text-muted small">{{ $lead->client->phone }}</span>
                            @else
                                {{ $lead->contact_name ?: '—' }}<br>
                                <span class="text-muted small">{{ $lead->contact_phone }}</span>
                            @endif
                        </td>
                        <td>
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
                            <span class="badge rounded-pill {{ $badge }}">{{ $lead->statusLabel() }}</span>
                        </td>
                        <td>{{ $lead->targetStore?->name ?? '—' }}</td>
                        <td class="text-nowrap small">
                            {{ $lead->created_at?->format('d.m.Y H:i') }}<br>
                            <span class="text-muted">{{ $lead->createdByUser?->name }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('contact-center.leads.show', $lead) }}" class="btn btn-sm btn-outline-primary">Открыть</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Заявок пока нет</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($leads->hasPages())
        <div class="card-footer bg-white">{{ $leads->links() }}</div>
    @endif
</div>
@endsection
