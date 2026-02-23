@extends('layouts.app')

@section('title', 'Начисления ФОТ')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-currency-dollar me-2"></i>Начисления ФОТ</h1>
    <div>
        <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary me-2">Сотрудники</a>
        <a href="{{ route('payroll-accruals.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Документ начисления</a>
    </div>
</div>
<p class="text-muted small">Учёт: начисления сотрудникам отображаются на счёте <strong>70</strong> «Расчёты с персоналом по оплате труда».</p>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>№</th><th>Период</th><th>Дата начисления</th><th>Сумма</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($accruals as $a)
                <tr>
                    <td>{{ $a->number }}</td>
                    <td>{{ $a->period_label }}</td>
                    <td>{{ \Carbon\Carbon::parse($a->accrual_date)->format('d.m.Y') }}</td>
                    <td>{{ number_format($a->total_amount, 2, ',', ' ') }} ₽</td>
                    <td><a href="{{ route('payroll-accruals.show', $a) }}" class="btn btn-sm btn-outline-primary">Подробнее</a></td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-muted">Нет начислений. <a href="{{ route('payroll-accruals.create') }}">Создать документ</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $accruals->links() }}
@endsection
