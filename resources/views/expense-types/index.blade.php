@extends('layouts.app')

@section('title', 'Виды расходов')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-list-ul me-2"></i>Виды расходов</h1>
    <a href="{{ route('expense-types.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Код</th><th>Название</th><th>Счёт учёта</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($expenseTypes as $et)
                <tr>
                    <td>{{ $et->code ?? '—' }}</td>
                    <td>{{ $et->name }}</td>
                    <td>{{ $et->account ? $et->account->code . ' ' . $et->account->name : '—' }}</td>
                    <td>
                        <a href="{{ route('expense-types.edit', $et) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                        @if(!$et->expenses()->exists())
                        <form action="{{ route('expense-types.destroy', $et) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить вид расхода?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-muted">Нет видов расходов. Добавьте первый.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
