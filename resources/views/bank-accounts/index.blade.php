@extends('layouts.app')

@section('title', 'Расчётные счета')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-bank me-2"></i>Расчётные счета</h1>
    <a href="{{ route('bank-accounts.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить счёт</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Название</th><th>Банк</th><th>Номер счёта</th><th>Магазин</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($bankAccounts as $ba)
                <tr>
                    <td>{{ $ba->name }}</td>
                    <td>{{ $ba->bank_name ?? '—' }}</td>
                    <td>{{ $ba->account_number ?? '—' }}</td>
                    <td>{{ $ba->store?->name ?? '—' }}</td>
                    <td>
                        <a href="{{ route('bank-accounts.statements.index', $ba) }}" class="btn btn-sm btn-outline-primary">Выписки</a>
                        <a href="{{ route('bank-accounts.edit', $ba) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                        @if(!$ba->bankStatements()->exists())
                        <form action="{{ route('bank-accounts.destroy', $ba) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить счёт?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-muted">Нет расчётных счетов. Добавьте первый.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
