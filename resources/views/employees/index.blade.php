@extends('layouts.app')

@section('title', 'Сотрудники (ФОТ)')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-person-badge me-2"></i>Сотрудники</h1>
    <div>
        <a href="{{ route('payroll-accruals.index') }}" class="btn btn-outline-primary me-2">Начисления ФОТ</a>
        <a href="{{ route('employees.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>ФИО</th><th>Должность</th><th>Магазин</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($employees as $emp)
                <tr>
                    <td>{{ $emp->full_name }}</td>
                    <td>{{ $emp->position ?? '—' }}</td>
                    <td>{{ $emp->store?->name ?? '—' }}</td>
                    <td>
                        <a href="{{ route('employees.edit', $emp) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                        <form action="{{ route('employees.destroy', $emp) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить сотрудника?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-muted">Нет сотрудников. Добавьте для начисления ФОТ.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
