@extends('layouts.app')

@section('title', 'Собрание — '.$report->title)

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item"><a href="{{ route('section.management') }}">Управление</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.reports.index') }}">Отчёты</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.reports.meetings.index') }}">Собрания</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $report->meeting_at?->format('d.m.Y H:i') }}</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">{{ $report->title ?: 'Собрание' }}</h1>
            <p class="text-muted small mb-0">
                {{ $report->meeting_at?->format('d.m.Y H:i') ?? '—' }}
                @if($report->room) · {{ $report->room }} @endif
                @if($report->processed_at) · обработано {{ $report->processed_at->format('d.m.Y H:i') }} @endif
            </p>
        </div>
        <a href="{{ route('management.reports.meetings.index') }}" class="btn btn-outline-secondary btn-sm">К журналу</a>
    </div>
</div>

@if($report->status === 'failed')
    <div class="alert alert-danger border-0 shadow-sm">{{ $report->error_message ?: 'Ошибка обработки.' }}</div>
@endif

@if($report->summary)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Краткий отчёт</h2>
            <div class="text-body" style="white-space: pre-wrap;">{{ $report->summary }}</div>
            @if(!empty($report->highlights))
                <hr>
                <h3 class="h6 text-muted">Тезисы</h3>
                <ul class="mb-0">
                    @foreach($report->highlights as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif

@if($report->transcript)
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Стенограмма по участникам</h2>
            <div class="font-monospace small bg-light rounded p-3" style="white-space: pre-wrap; max-height: 70vh; overflow: auto;">{{ $report->transcript }}</div>
        </div>
    </div>
@elseif($report->status === 'processing')
    <div class="alert alert-warning border-0 shadow-sm">Собрание ещё обрабатывается…</div>
@endif
@endsection
