@extends('layouts.app')

@section('title', 'Карточка счёта ' . $account->code)

@section('content')
<h1 class="h4 mb-4"><i class="bi bi-journal-ruled me-2"></i>Карточка счёта {{ $account->code }} — {{ $account->name }}</h1>

<div class="mb-3">
    <a href="{{ route('chart-of-accounts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> План счетов</a>
    <a href="{{ route('chart-of-accounts.turnover-balance', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'store_id' => $storeId, 'client_id' => $clientId]) }}" class="btn btn-outline-primary">Оборотно-сальдовая ведомость</a>
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
</form>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Итоги за период</span>
        <span class="small text-muted">{{ $dateFrom }} — {{ $dateTo }}</span>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <strong>Сальдо на начало</strong>
                <div class="h5">{{ number_format($balanceBefore, 2, ',', ' ') }} ₽</div>
            </div>
            <div class="col-md-3">
                <strong>Оборот по дебету</strong>
                <div class="h5 text-success">{{ number_format($totalDebit, 2, ',', ' ') }} ₽</div>
            </div>
            <div class="col-md-3">
                <strong>Оборот по кредиту</strong>
                <div class="h5 text-danger">{{ number_format($totalCredit, 2, ',', ' ') }} ₽</div>
            </div>
            <div class="col-md-3">
                <strong>Сальдо на конец</strong>
                <div class="h5">{{ number_format($balanceAfter, 2, ',', ' ') }} ₽</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Движения по счёту</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Дата</th>
                        <th>Документ</th>
                        <th>Магазин</th>
                        <th>Клиент</th>
                        <th class="text-end">Дебет</th>
                        <th class="text-end">Кредит</th>
                        <th>Комментарий</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $e)
                    <tr>
                        <td>{{ $e->entry_date ? \Carbon\Carbon::parse($e->entry_date)->format('d.m.Y') : '—' }}</td>
                        <td>
                            @if($e->document_type && $e->document_id)
                                {{ $e->document_type }} #{{ $e->document_id }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $e->store?->name ?? '—' }}</td>
                        <td>{{ $e->client ? $e->client->last_name . ' ' . $e->client->first_name : '—' }}</td>
                        <td class="text-end">{{ $e->debit > 0 ? number_format($e->debit, 2, ',', ' ') : '—' }}</td>
                        <td class="text-end">{{ $e->credit > 0 ? number_format($e->credit, 2, ',', ' ') : '—' }}</td>
                        <td class="small text-muted">{{ Str::limit($e->comment, 50) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted text-center py-4">Нет движений за выбранный период.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($entries->hasPages())
    <div class="card-footer">
        {{ $entries->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
