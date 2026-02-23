@extends('layouts.app')

@section('title', 'Колл-центр')

@section('content')
<h1 class="h4 mb-4">Колл-центр</h1>

<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <a href="{{ route('call-center.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Зафиксировать обращение</a>
    <a href="{{ route('call-center.analytics') }}" class="btn btn-outline-primary"><i class="bi bi-bar-chart"></i> Аналитика</a>
    <form method="post" action="{{ route('call-center.sync-mts-calls') }}" class="d-inline">
        @csrf
        <select name="days" class="form-select form-select-sm d-inline-block" style="width:auto">
            <option value="1">За последний день</option>
            <option value="7">За 7 дней</option>
            <option value="30">За 30 дней</option>
        </select>
        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-telephone-inbound"></i> Загрузить звонки с MTS</button>
    </form>
    <form method="post" action="{{ route('call-center.sync-mts-recordings') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-mic"></i> Загрузить записи (за 7 дней)</button>
    </form>
    <form method="post" action="{{ route('call-center.clear-mts-contacts') }}" class="d-inline" onsubmit="return confirm('Удалить все телефонные обращения, загруженные из MTS?');">
        @csrf
        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Очистить звонки MTS</button>
    </form>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="channel" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все каналы</option>
            @foreach(\App\Models\CallCenterContact::CHANNELS as $k => $v)
                <option value="{{ $k }}" {{ request('channel') === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <select name="call_status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все статусы вызова</option>
            @foreach(\App\Models\CallCenterContact::CALL_STATUSES as $k => $v)
                <option value="{{ $k }}" {{ request('call_status') === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <select name="outcome" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все исходы</option>
            @foreach(\App\Models\CallCenterContact::OUTCOMES as $k => $v)
                <option value="{{ $k }}" {{ request('outcome') === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <input type="date" name="date_from" class="form-control form-control-sm" style="width:auto" value="{{ request('date_from') }}" onchange="this.form.submit()" placeholder="Дата с">
    </div>
    <div class="col-auto">
        <input type="date" name="date_to" class="form-control form-control-sm" style="width:auto" value="{{ request('date_to') }}" onchange="this.form.submit()" placeholder="Дата по">
    </div>
</form>

@if($contacts->isEmpty())
    <p class="text-muted">Нет обращений за выбранный период.</p>
@else
<table class="table table-hover">
    <thead>
        <tr>
            <th>Дата</th>
            <th>Канал</th>
            <th>Длительность</th>
            <th>Контакт</th>
            <th>Магазин</th>
            <th>Исход</th>
            <th>Сделка</th>
            <th>Запись</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        @foreach($contacts as $c)
        <tr>
            <td>{{ $c->contact_date ? \Carbon\Carbon::parse($c->contact_date)->format('d.m.Y H:i') : '—' }}</td>
            <td>
                <span class="badge bg-secondary">{{ $c->channel_label }}</span>
                @if($c->direction === 'outgoing')<span class="badge bg-light text-dark">исх.</span>@endif
            </td>
            <td>
                @if($c->call_duration_sec !== null && $c->call_duration_sec > 1)
                    <span class="badge bg-success">{{ $c->call_duration_sec }} сек</span>
                @elseif($c->call_status === 'missed' || ($c->call_duration_sec !== null && $c->call_duration_sec <= 1))
                    <span class="badge bg-warning text-dark">Пропущен</span>
                @else
                    <span class="text-muted">—</span>
                @endif
            </td>
            <td>
                @if($c->client_id)
                    <a href="{{ route('clients.show', $c->client) }}">{{ $c->client->full_name }}</a>
                    @if($c->client->phone)<br><small class="text-muted">{{ $c->client->phone }}</small>@endif
                @else
                    {{ $c->contact_name ?: $c->contact_phone ?: '—' }}
                @endif
            </td>
            <td>{{ $c->store?->name ?? '—' }}</td>
            <td>{{ $c->outcome_label }}</td>
            <td>
                @if($c->pawn_contract_id)
                    <a href="{{ route('pawn-contracts.show', $c->pawnContract) }}">{{ $c->pawnContract->contract_number }}</a>
                @elseif($c->purchase_contract_id)
                    <a href="{{ route('purchase-contracts.show', $c->purchaseContract) }}">{{ $c->purchaseContract->contract_number }}</a>
                @elseif($c->commission_contract_id)
                    <a href="{{ route('commission-contracts.show', $c->commissionContract) }}">{{ $c->commissionContract->contract_number }}</a>
                @else
                    —
                @endif
            </td>
            <td>
                @if($c->recording_path)
                    <a href="{{ route('call-center.show', $c) }}#recording" title="Слушать запись"><i class="bi bi-mic-fill text-success"></i></a>
                @elseif($c->ext_tracking_id)
                    <span class="text-muted" title="Есть ID записи MTS, загрузите записи"><i class="bi bi-mic"></i></span>
                @else
                    —
                @endif
            </td>
            <td>
                <a href="{{ route('call-center.show', $c) }}" class="btn btn-sm btn-outline-secondary">Подробнее</a>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $contacts->links() }}
@endif
@endsection
