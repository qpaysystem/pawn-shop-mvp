@extends('layouts.app')

@section('title', 'Начисление ФОТ ' . $payrollAccrual->number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Начисление ФОТ {{ $payrollAccrual->number }}</h1>
    <a href="{{ route('payroll-accruals.index') }}" class="btn btn-outline-secondary">К списку</a>
</div>
<div class="card mb-3">
    <div class="card-body">
        <p><strong>Период:</strong> {{ $payrollAccrual->period_label }}</p>
        <p><strong>Дата начисления:</strong> {{ \Carbon\Carbon::parse($payrollAccrual->accrual_date)->format('d.m.Y') }}</p>
        <p><strong>Итого:</strong> {{ number_format($payrollAccrual->total_amount, 2, ',', ' ') }} ₽</p>
        <p class="text-muted small mb-0"><strong>Учёт:</strong> начисления отображаются на счёте <strong>70</strong> «Расчёты с персоналом по оплате труда».</p>
        @if($payrollAccrual->notes)<p><strong>Примечание:</strong> {{ $payrollAccrual->notes }}</p>@endif
    </div>
</div>
<div class="card">
    <div class="card-header">По сотрудникам</div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Сотрудник</th><th>Сумма</th></tr></thead>
            <tbody>
                @foreach($payrollAccrual->items as $item)
                <tr>
                    <td>{{ $item->employee->full_name }}</td>
                    <td>{{ number_format($item->amount, 2, ',', ' ') }} ₽</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
