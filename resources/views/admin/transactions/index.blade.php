@extends('layouts.admin')
@section('title', 'Транзакции')
@section('content')
<h1 class="h4 mb-4">Журнал транзакций</h1>

<form method="get" action="{{ route('admin.transactions.index') }}" class="row g-2 mb-4">
    <div class="col-auto">
        <label class="form-label visually-hidden">Клиент</label>
        <select name="client_id" class="form-select">
            <option value="">Все клиенты</option>
            @foreach($clients as $c)
                <option value="{{ $c->id }}" @selected(request('client_id') == $c->id)>{{ $c->full_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label visually-hidden">Тип операции</label>
        <select name="operation_type" class="form-select">
            <option value="">Все типы</option>
            @foreach(\App\Models\BalanceTransaction::operationTypeLabels() as $value => $label)
                <option value="{{ $value }}" @selected(request('operation_type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label visually-hidden">Тип (пополнение/списание)</label>
        <select name="type" class="form-select">
            <option value="">Все</option>
            <option value="deposit" @selected(request('type') === 'deposit')>Пополнение</option>
            <option value="withdraw" @selected(request('type') === 'withdraw')>Списание</option>
        </select>
    </div>
    <div class="col-auto">
        <label class="form-label visually-hidden">Дата с</label>
        <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" placeholder="С">
    </div>
    <div class="col-auto">
        <label class="form-label visually-hidden">По</label>
        <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" placeholder="По">
    </div>
    <div class="col-auto d-flex align-items-end">
        <button type="submit" class="btn btn-primary me-1"><i class="bi bi-search"></i> Показать</button>
        <a href="{{ route('admin.transactions.index') }}" class="btn btn-outline-secondary">Сбросить</a>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Дата и время</th>
                        <th>Клиент</th>
                        <th>Тип операции</th>
                        <th>Залог</th>
                        <th>Дней</th>
                        <th>Дата возврата</th>
                        <th class="text-end">Сумма</th>
                        <th class="text-end">Баланс после</th>
                        <th>Оператор</th>
                        <th>Комментарий</th>
                        <th class="text-center" style="width: 100px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                    <tr>
                        <td>{{ $t->created_at->format('d.m.Y H:i') }}</td>
                        <td>
                            <a href="{{ route('admin.clients.show', $t->client) }}">{{ $t->client->full_name }}</a>
                        </td>
                        <td>{{ $t->operation_type_label }}</td>
                        <td>
                            @if($t->operation_type === \App\Models\BalanceTransaction::OPERATION_PROJECT_EXPENSE)
                                @if($t->project)<a href="{{ route('admin.projects.show', $t->project) }}">{{ $t->project->name }}</a>@if($t->projectExpenseItem) — {{ $t->projectExpenseItem->name }}@endif @else — @endif
                            @else
                                @if($t->product)<a href="{{ route('admin.products.edit', $t->product) }}">{{ Str::limit($t->product->name, 25) }}</a>@else — @endif
                            @endif
                        </td>
                        <td>{{ $t->loan_days ?? '—' }}</td>
                        <td>{{ $t->loan_due_at?->format('d.m.Y') ?? '—' }}</td>
                        <td class="text-end {{ $t->type === 'deposit' ? 'text-success' : 'text-danger' }}">
                            {{ $t->type === 'deposit' ? '+' : '−' }}{{ number_format($t->amount, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}
                        </td>
                        <td class="text-end">{{ number_format($t->balance_after, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</td>
                        <td>{{ $t->user?->name ?? '—' }}</td>
                        <td class="text-muted small">{{ Str::limit($t->comment, 50) }}</td>
                        <td class="text-center">
                            <form method="post" action="{{ route('admin.transactions.destroy', $t) }}" class="d-inline" onsubmit="return confirm('Удалить эту транзакцию? Баланс клиента будет пересчитан.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Удалить транзакцию"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="text-center text-muted py-4">Нет операций за выбранный период</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($transactions->hasPages())
        <div class="card-footer">
            {{ $transactions->links() }}
        </div>
    @endif
</div>
@endsection
