@extends('layouts.app')

@section('title', 'Расходы')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-cash-expense me-2"></i>Расходы</h1>
    <div>
        <a href="{{ route('expense-types.index') }}" class="btn btn-outline-secondary me-2">Виды расходов</a>
        <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Начислить расход</a>
    </div>
</div>
<form method="get" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="expense_type_id" class="form-select form-select-sm">
            <option value="">Все виды</option>
            @foreach($expenseTypes as $et)
            <option value="{{ $et->id }}" @selected(request('expense_type_id') == $et->id)>{{ $et->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <select name="store_id" class="form-select form-select-sm">
            <option value="">Все магазины</option>
            @foreach($stores as $s)
            <option value="{{ $s->id }}" @selected(request('store_id') == $s->id)>{{ $s->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-outline-primary">Показать</button></div>
</form>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>№</th><th>Дата</th><th>Вид расхода</th><th>Магазин</th><th>Сумма</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($expenses as $e)
                <tr>
                    <td>{{ $e->number }}</td>
                    <td>{{ \Carbon\Carbon::parse($e->expense_date)->format('d.m.Y') }}</td>
                    <td>{{ $e->expenseType->name }}</td>
                    <td>{{ $e->store?->name ?? '—' }}</td>
                    <td>{{ number_format($e->amount, 2, ',', ' ') }} ₽</td>
                    <td><a href="{{ route('expenses.show', $e) }}" class="btn btn-sm btn-outline-primary">Подробнее</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-muted">Нет документов. <a href="{{ route('expenses.create') }}">Начислить расход</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $expenses->links() }}
@endsection
