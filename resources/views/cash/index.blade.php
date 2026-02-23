@extends('layouts.app')

@section('title', 'Касса')

@section('content')
<h1 class="h4 mb-4">Кассовые операции</h1>

@if(auth()->user()->canProcessSales())
<div class="mb-3">
    <a href="{{ route('cash.create', $store ? ['store_id' => $store->id] : []) }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Новый приход / расход</a>
    <a href="{{ route('cash.report') }}" class="btn btn-outline-primary"><i class="bi bi-bar-chart"></i> Отчёт по кассам</a>
</div>
@endif

@if($stores->isEmpty())
    <p class="text-muted">Нет доступных магазинов.</p>
@else
<form method="get" class="row g-3 mb-4">
    <div class="col-auto">
        <label class="form-label">Магазин</label>
        <select name="store_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            @foreach($stores as $s)
                <option value="{{ $s->id }}" {{ ($store && $store->id == $s->id) ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label">Клиент</label>
        <select name="client_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все</option>
            @foreach(\App\Models\Client::orderBy('full_name')->get(['id','full_name']) as $c)
                <option value="{{ $c->id }}" {{ request('client_id') == $c->id ? 'selected' : '' }}>{{ $c->full_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label">Тип</label>
        <select name="direction" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все</option>
            <option value="income" {{ request('direction') === 'income' ? 'selected' : '' }}>Приход</option>
            <option value="expense" {{ request('direction') === 'expense' ? 'selected' : '' }}>Расход</option>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label">Дата с</label>
        <input type="date" name="date_from" class="form-control form-control-sm" style="width:auto" value="{{ request('date_from') }}" onchange="this.form.submit()">
    </div>
    <div class="col-auto">
        <label class="form-label">Дата по</label>
        <input type="date" name="date_to" class="form-control form-control-sm" style="width:auto" value="{{ request('date_to') }}" onchange="this.form.submit()">
    </div>
    @if(request()->hasAny(['direction','date_from','date_to','client_id']))
    <div class="col-auto align-self-end">
        <a href="{{ route('cash.index', ['store_id' => $store?->id]) }}" class="btn btn-sm btn-outline-secondary">Сбросить</a>
    </div>
    @endif
</form>

@if($store || isset($filterClient))
<div class="card mb-4">
    <div class="card-body py-3">
        @if($store)
        <strong>Кассовый баланс ({{ $store->name }}):</strong>
        <span class="fs-4 ms-2 {{ $balance >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($balance, 0, ',', ' ') }} ₽</span>
        @endif
        @if(isset($filterClient) && $filterClient)
        <span class="ms-4">|</span>
        <strong class="ms-4">Баланс клиента {{ $filterClient->full_name }}:</strong>
        <span class="fs-4 ms-2 {{ ($clientBalance ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($clientBalance ?? 0, 0, ',', ' ') }} ₽</span>
        @endif
    </div>
</div>
@endif

<table class="table table-hover">
    <thead>
        <tr>
            <th>Дата</th>
            <th>№ документа</th>
            @if(isset($filterClient) && $filterClient)
                <th>Магазин</th>
            @else
                <th>Клиент</th>
            @endif
            <th>Вид операции</th>
            <th>Приход</th>
            <th>Расход</th>
            <th>Комментарий</th>
            <th>Создал</th>
            @if(auth()->user()->canProcessSales())<th></th>@endif
        </tr>
    </thead>
    <tbody>
        @forelse($documents as $d)
        <tr>
            <td>{{ \Carbon\Carbon::parse($d->document_date)->format('d.m.Y') }}</td>
            <td>{{ $d->document_number }}</td>
            <td>
                @if(isset($filterClient) && $filterClient)
                    {{ $d->store?->name ?? '—' }}
                @elseif($d->client)
                    <a href="{{ route('clients.show', $d->client) }}">{{ $d->client->full_name }}</a>
                @else
                    —
                @endif
            </td>
            <td>
                {{ $d->operationType->name }}
                @if($d->isTransfer() && $d->targetStore)
                    <br><small class="text-muted">{{ $d->store?->name }} → {{ $d->targetStore->name }}</small>
                @endif
            </td>
            <td>@if($d->isIncome()){{ number_format($d->amount, 0, ',', ' ') }} ₽@else—@endif</td>
            <td>@if($d->isExpense()){{ number_format($d->amount, 0, ',', ' ') }} ₽@else—@endif</td>
            <td class="text-muted small">{{ Str::limit($d->comment, 50) }}</td>
            <td class="small">{{ $d->createdByUser?->name ?? '—' }}</td>
            @if(auth()->user()->canProcessSales())
            <td>
                <form action="{{ route('cash.destroy', $d) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить документ?')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button></form>
            </td>
            @endif
        </tr>
        @empty
        <tr><td colspan="{{ auth()->user()->canProcessSales() ? 9 : 8 }}" class="text-muted text-center py-4">Нет документов за выбранный период.</td></tr>
        @endforelse
    </tbody>
</table>
{{ $documents->links() }}
@endif
@endsection
