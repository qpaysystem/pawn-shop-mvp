@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item"><a href="{{ route('section.management') }}">Управление</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.reports.index') }}">Отчёты</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $pageTitle }}</li>
        </ol>
    </nav>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h1 class="h3 mb-1">{{ $pageTitle }}</h1>
            <p class="text-muted mb-0">Выданные залоги и выкупы за период · активные договоры на текущую дату</p>
        </div>
        <a href="{{ route('management.reports.index') }}" class="btn btn-outline-secondary btn-sm">К списку отчётов</a>
    </div>
</div>

@include('management.reports.lombard._filters')

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Выдано залогов</div>
                <div class="fs-4 fw-semibold">{{ number_format($data['totals']['issued_count'], 0, ',', ' ') }}</div>
                <div class="small text-muted">{{ number_format($data['totals']['issued_amount'], 0, ',', ' ') }} ₽</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Выкуплено</div>
                <div class="fs-4 fw-semibold">{{ number_format($data['totals']['redeemed_count'], 0, ',', ' ') }}</div>
                <div class="small text-muted">{{ number_format($data['totals']['redeemed_amount'], 0, ',', ' ') }} ₽</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Активных залогов сейчас</div>
                <div class="fs-4 fw-semibold">{{ number_format($data['totals']['active_count'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Период</div>
                <div class="fw-semibold">{{ \Carbon\Carbon::parse($data['date_from'])->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($data['date_to'])->format('d.m.Y') }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">По торговым точкам</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Точка</th>
                        <th class="text-end">Выдано</th>
                        <th class="text-end">Сумма займов</th>
                        <th class="text-end">Выкуплено</th>
                        <th class="text-end">Сумма выкупов</th>
                        <th class="text-end">Активных</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['by_store'] as $row)
                        <tr>
                            <td>{{ $row['store_name'] }}</td>
                            <td class="text-end">{{ number_format($row['issued_count'], 0, ',', ' ') }}</td>
                            <td class="text-end">{{ number_format($row['issued_amount'], 0, ',', ' ') }} ₽</td>
                            <td class="text-end">{{ number_format($row['redeemed_count'], 0, ',', ' ') }}</td>
                            <td class="text-end">{{ number_format($row['redeemed_amount'], 0, ',', ' ') }} ₽</td>
                            <td class="text-end">{{ number_format($row['active_count'], 0, ',', ' ') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted text-center py-4">Нет данных</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
