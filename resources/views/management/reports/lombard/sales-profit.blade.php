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
            <p class="text-muted mb-0">Маржа: выручка от реализации минус себестоимость (скупка или первоначальная цена)</p>
        </div>
        <a href="{{ route('management.reports.index') }}" class="btn btn-outline-secondary btn-sm">К списку отчётов</a>
    </div>
</div>

@include('management.reports.lombard._filters')

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Продаж</div>
                <div class="fs-4 fw-semibold">{{ number_format($data['totals']['count'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Выручка</div>
                <div class="fs-4 fw-semibold">{{ number_format($data['totals']['revenue'], 0, ',', ' ') }} ₽</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Себестоимость</div>
                <div class="fs-4 fw-semibold">{{ number_format($data['totals']['cost'], 0, ',', ' ') }} ₽</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-success border-opacity-25">
            <div class="card-body">
                <div class="text-muted small">Прибыль</div>
                <div class="fs-4 fw-semibold text-success">{{ number_format($data['totals']['profit'], 0, ',', ' ') }} ₽</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">По точкам</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Точка</th>
                        <th class="text-end">Продаж</th>
                        <th class="text-end">Выручка</th>
                        <th class="text-end">Себестоимость</th>
                        <th class="text-end">Прибыль</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['by_store'] as $row)
                        @if($row['count'] > 0)
                        <tr>
                            <td>{{ $row['store_name'] }}</td>
                            <td class="text-end">{{ $row['count'] }}</td>
                            <td class="text-end">{{ number_format($row['revenue'], 0, ',', ' ') }} ₽</td>
                            <td class="text-end">{{ number_format($row['cost'], 0, ',', ' ') }} ₽</td>
                            <td class="text-end text-success fw-semibold">{{ number_format($row['profit'], 0, ',', ' ') }} ₽</td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between">
        <span>Детализация</span>
        <span class="text-muted small">показано {{ count($data['rows']) }} из {{ $data['rows_total'] }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Дата</th>
                        <th>№ реализации</th>
                        <th>Клиент</th>
                        <th>Товар</th>
                        <th class="text-end">Себест.</th>
                        <th class="text-end">Продажа</th>
                        <th class="text-end">Прибыль</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['rows'] as $row)
                        <tr>
                            <td class="text-nowrap">{{ $row['sale_date'] }}</td>
                            <td>{{ $row['contract_number'] }}</td>
                            <td>{{ Str::limit($row['client_name'], 28) }}</td>
                            <td>{{ Str::limit($row['item_name'], 36) }}</td>
                            <td class="text-end">{{ number_format($row['cost'], 0, ',', ' ') }}</td>
                            <td class="text-end">{{ number_format($row['revenue'], 0, ',', ' ') }}</td>
                            <td class="text-end {{ $row['profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($row['profit'], 0, ',', ' ') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted text-center py-4">За период продаж нет</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
