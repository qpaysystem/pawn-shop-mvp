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
            <p class="text-muted mb-0">Остатки залогов и скупки · фильтры по точке, дате поступления, виду операции и товара</p>
        </div>
        <a href="{{ route('management.reports.index') }}" class="btn btn-outline-secondary btn-sm">К списку отчётов</a>
    </div>
</div>

@include('management.reports.lombard._inventory_filters')

@if($data['truncated'] ?? false)
    <div class="alert alert-warning py-2">
        Показаны первые {{ count($data['rows']) }} из {{ number_format($data['rows_total'], 0, ',', ' ') }} позиций. Сузьте фильтры для полного списка.
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Позиций</div>
                <div class="fs-4 fw-semibold">{{ number_format($data['totals']['count'], 0, ',', ' ') }}</div>
                <div class="small text-muted">залог {{ number_format($data['totals']['pawn_count'], 0, ',', ' ') }} · скупка {{ number_format($data['totals']['purchase_count'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Сумма</div>
                <div class="fs-4 fw-semibold">{{ number_format($data['totals']['amount'], 0, ',', ' ') }} ₽</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Просрочено (залоги)</div>
                <div class="fs-4 fw-semibold text-danger">{{ number_format($data['totals']['overdue_count'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Период поступления</div>
                <div class="fw-semibold">
                    @if($data['date_from'] && $data['date_to'])
                        {{ \Carbon\Carbon::parse($data['date_from'])->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($data['date_to'])->format('d.m.Y') }}
                    @else
                        все даты
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">По торговым точкам</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Точка</th>
                                <th class="text-end">Позиций</th>
                                <th class="text-end">Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data['by_store'] as $row)
                                <tr>
                                    <td>{{ $row['store_name'] }}</td>
                                    <td class="text-end">{{ number_format($row['count'], 0, ',', ' ') }}</td>
                                    <td class="text-end">{{ number_format($row['amount'], 0, ',', ' ') }} ₽</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center py-3">Нет данных</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">По виду товара</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Вид</th>
                                <th class="text-end">Позиций</th>
                                <th class="text-end">Сумма</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data['by_item_kind'] as $row)
                                <tr>
                                    <td>{{ $row['item_kind_label'] }}</td>
                                    <td class="text-end">{{ number_format($row['count'], 0, ',', ' ') }}</td>
                                    <td class="text-end">{{ number_format($row['amount'], 0, ',', ' ') }} ₽</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center py-3">Нет данных</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span>Реестр позиций</span>
        <span class="text-muted small">показано {{ count($data['rows']) }} @if(($data['rows_total'] ?? 0) > count($data['rows'])) из {{ number_format($data['rows_total'], 0, ',', ' ') }} @endif</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Точка</th>
                        <th>Тип</th>
                        <th>Документ</th>
                        <th>Статус</th>
                        <th>Вид товара</th>
                        <th>Наименование</th>
                        <th>Клиент</th>
                        <th>Поступление</th>
                        <th>Срок</th>
                        <th class="text-end">Дней</th>
                        <th class="text-end">Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data['rows'] as $row)
                        <tr>
                            <td>{{ $row['store_name'] }}</td>
                            <td>{{ $row['stock_kind_label'] }}</td>
                            <td class="text-nowrap">{{ $row['contract_number'] }}</td>
                            <td class="{{ ($row['status_code'] ?? '') === 'overdue' ? 'text-danger' : '' }}">{{ $row['status'] }}</td>
                            <td>{{ $row['item_kind_label'] }}</td>
                            <td>{{ $row['item_name'] }}</td>
                            <td>{{ $row['client_name'] }}</td>
                            <td class="text-nowrap">{{ $row['receipt_date'] }}</td>
                            <td class="text-nowrap">{{ $row['expiry_date'] }}</td>
                            <td class="text-end">{{ $row['days_in_stock'] !== null ? number_format($row['days_in_stock'], 0, ',', ' ') : '—' }}</td>
                            <td class="text-end text-nowrap">{{ number_format($row['amount'], 0, ',', ' ') }} ₽</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-muted text-center py-4">По выбранным фильтрам позиций не найдено</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
