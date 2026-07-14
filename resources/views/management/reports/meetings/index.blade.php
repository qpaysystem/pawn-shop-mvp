@extends('layouts.app')

@section('title', 'Собрания')

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item"><a href="{{ route('section.management') }}">Управление</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.reports.index') }}">Отчёты</a></li>
            <li class="breadcrumb-item active" aria-current="page">Собрания</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">Собрания</h1>
            <p class="text-muted small mb-0">Журнал видеособраний с conf.nnfm.pro: транскрипция по участникам и краткий отчёт ИИ.</p>
        </div>
        <form method="post" action="{{ route('management.reports.meetings.sync-latest') }}" onsubmit="return confirm('Запустить обработку последнего собрания? Займёт 2–5 минут, результат появится после обновления страницы.');">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm" @if($syncRunning ?? false) disabled @endif>
                <i class="bi bi-cloud-download me-1"></i>
                @if($syncRunning ?? false)
                    Обработка…
                @else
                    Загрузить последнее собрание
                @endif
            </button>
        </form>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
@endif
@if(session('info'))
    <div class="alert alert-info border-0 shadow-sm">{{ session('info') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div>
@endif
@if($syncRunning ?? false)
    <div class="alert alert-warning border-0 shadow-sm mb-3">
        Идёт обработка собрания (скачивание, транскрипция, отчёт ИИ). Обновите страницу через 2–5 минут.
    </div>
@endif

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        @if($reports->isEmpty())
            <div class="p-4 text-muted">
                Журнал пуст. Нажмите «Загрузить последнее собрание» для тестовой обработки.
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Дата и время</th>
                            <th>Название</th>
                            <th>Комната</th>
                            <th>Статус</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reports as $report)
                            <tr>
                                <td class="text-nowrap">{{ $report->meeting_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td>{{ $report->title ?: '—' }}</td>
                                <td>{{ $report->room ?: '—' }}</td>
                                <td>
                                    @if($report->status === 'completed')
                                        <span class="badge text-bg-success">Готово</span>
                                    @elseif($report->status === 'processing')
                                        <span class="badge text-bg-warning">Обработка</span>
                                    @elseif($report->status === 'failed')
                                        <span class="badge text-bg-danger">Ошибка</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ $report->status }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('management.reports.meetings.show', $report) }}" class="btn btn-outline-primary btn-sm">Открыть</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($reports->hasPages())
                <div class="p-3 border-top">{{ $reports->links() }}</div>
            @endif
        @endif
    </div>
</div>
@endsection
