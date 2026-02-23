@extends('layouts.app')

@section('title', 'Документ начисления ФОТ')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Документ начисления ФОТ</h1>
    <a href="{{ route('payroll-accruals.index') }}" class="btn btn-outline-secondary">К списку</a>
</div>
<form method="post" action="{{ route('payroll-accruals.store') }}">
    @csrf
    <div class="card mb-3">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Месяц</label>
                    <select name="period_month" class="form-select" required>
                        @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected($periodMonth == $m)>{{ $m }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Год</label>
                    <input type="number" name="period_year" class="form-control" value="{{ $periodYear }}" min="2020" max="2100" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Дата начисления *</label>
                    <input type="date" name="accrual_date" class="form-control" value="{{ old('accrual_date', date('Y-m-d')) }}" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Примечание</label>
                <textarea name="notes" class="form-control" rows="1">{{ old('notes') }}</textarea>
            </div>
            <hr>
            <h6 class="mb-3">Начисления по сотрудникам</h6>
            <table class="table table-sm">
                <thead><tr><th>Сотрудник</th><th>Сумма (₽)</th></tr></thead>
                <tbody>
                    @foreach($employees as $emp)
                    <tr>
                        <td>{{ $emp->full_name }} @if($emp->position)<small class="text-muted">({{ $emp->position }})</small>@endif</td>
                        <td style="width:180px">
                            <input type="hidden" name="items[{{ $loop->index }}][employee_id]" value="{{ $emp->id }}">
                            <input type="number" name="items[{{ $loop->index }}][amount]" class="form-control form-control-sm" step="0.01" min="0" value="0">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($employees->isEmpty())
            <p class="text-muted mb-0">Нет активных сотрудников. <a href="{{ route('employees.create') }}">Добавьте сотрудников</a>.</p>
            @endif
        </div>
    </div>
    @if($employees->isNotEmpty())
    <button type="submit" class="btn btn-primary">Создать документ</button>
    @endif
</form>
@endsection
