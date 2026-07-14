@extends('layouts.app')

@section('title', $pageTitle)

@push('styles')
<style>
    .acuerdo-report-wrap {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        padding: 1rem;
        overflow: auto;
    }
    .acuerdo-report-html {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 13px;
        line-height: 1.35;
        color: #000;
    }
    .acuerdo-report-html table {
        border-collapse: collapse;
        width: auto;
        min-width: 100%;
        background: #fff;
    }
    .acuerdo-report-html td,
    .acuerdo-report-html th {
        border: 1px solid #808080;
        padding: 2px 6px;
        vertical-align: middle;
        white-space: nowrap;
    }
    .acuerdo-report-html a {
        color: #224d66;
    }
</style>
@endpush

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
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h1 class="h3 mb-0">{{ $pageTitle }}</h1>
        <a href="{{ route('management.reports.index') }}" class="btn btn-outline-secondary btn-sm">К списку отчётов</a>
    </div>
    <p class="text-muted small mb-0 mt-2">
        Источник: <a href="{{ config('services.acuerdo.info_base_url') }}" target="_blank" rel="noopener">info.acuerdo.pro</a>.
        Данные загружаются при каждом открытии страницы.
    </p>
</div>

@if(!($result['ok'] ?? false))
    <div class="alert alert-danger border-0 shadow-sm">
        {{ $result['error'] ?? 'Не удалось загрузить отчёт.' }}
    </div>
    <p class="text-muted small">
        Настройте доступ в <a href="{{ route('settings.system.index') }}">системных настройках</a>
        (блок Acuerdo) или в <code>app/.env</code>: <code>ACUERDO_USERNAME</code>, <code>ACUERDO_PASSWORD</code>.
    </p>
@else
    <div class="alert alert-light border shadow-sm py-2 mb-3">
        <div class="fw-semibold">{{ $result['report_title'] ?? $pageTitle }}</div>
        <div class="small text-muted">
            Обновлено: {{ $result['fetched_at'] ?? '—' }}
            @if(!empty($result['report_path']))
                · файл: {{ $result['report_path'] }}
            @endif
        </div>
    </div>
    <div class="acuerdo-report-wrap">
        <div class="acuerdo-report-html">
            {!! $result['html'] ?? '' !!}
        </div>
    </div>
@endif
@endsection
