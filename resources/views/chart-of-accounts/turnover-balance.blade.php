@extends('layouts.app')

@section('title', 'Оборотно-сальдовая ведомость')

@section('content')
<h1 class="h4 mb-4"><i class="bi bi-table me-2"></i>Оборотно-сальдовая ведомость</h1>

<div class="mb-3">
    <a href="{{ route('chart-of-accounts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> План счетов</a>
</div>

<form method="get" class="row g-3 mb-4">
    <div class="col-auto">
        <label class="form-label">Дата с</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}" onchange="this.form.submit()">
    </div>
    <div class="col-auto">
        <label class="form-label">Дата по</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}" onchange="this.form.submit()">
    </div>
    @if($stores->isNotEmpty())
    <div class="col-auto">
        <label class="form-label">Магазин</label>
        <select name="store_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все</option>
            @foreach($stores as $s)
                <option value="{{ $s->id }}" {{ $storeId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div class="col-auto">
        <label class="form-label">Клиент</label>
        <select name="client_id" class="form-select form-select-sm" style="width:auto; max-width:220px" onchange="this.form.submit()">
            <option value="">Все</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" {{ $clientId == $c->id ? 'selected' : '' }}>{{ $c->last_name }} {{ $c->first_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto align-self-end">
        <button type="submit" class="btn btn-primary btn-sm">Показать</button>
    </div>
</form>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Оборотно-сальдовая ведомость</span>
        <span class="small text-muted">{{ $dateFrom }} — {{ $dateTo }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Счёт</th>
                        <th>Наименование</th>
                        <th class="text-end">Сальдо на начало</th>
                        <th class="text-end">Оборот дебет</th>
                        <th class="text-end">Оборот кредит</th>
                        <th class="text-end">Сальдо на конец</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr>
                        <td><strong>{{ $row->account->code }}</strong></td>
                        <td>{{ $row->account->name }}</td>
                        <td class="text-end">{{ number_format($row->balance_before, 2, ',', ' ') }}</td>
                        <td class="text-end">{{ number_format($row->debit, 2, ',', ' ') }}</td>
                        <td class="text-end">{{ number_format($row->credit, 2, ',', ' ') }}</td>
                        <td class="text-end"><strong>{{ number_format($row->balance_after, 2, ',', ' ') }}</strong></td>
                        <td>
                            <a href="{{ route('chart-of-accounts.show', ['account' => $row->account, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'store_id' => $storeId, 'client_id' => $clientId]) }}" class="btn btn-sm btn-outline-primary">Карточка</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-muted text-center py-4">Нет данных за период. Проводки создаются при кассовых операциях, выдаче займов, скупке и продажах.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
