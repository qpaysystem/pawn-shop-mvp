@extends('layouts.app')

@section('title', $task->title)

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item"><a href="{{ route('section.management') }}">Управление</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.tasks.index') }}">Задачи</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $task->title }}</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-2">{{ $task->title }}</h1>
            <span class="badge {{ $task->statusBadgeClass() }}">{{ $task->statusLabel() }}</span>
            @if($task->isOverdue())
                <span class="badge text-bg-danger">Просрочено</span>
            @endif
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('management.tasks.board') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-kanban"></i> Канбан</a>
            <a href="{{ route('management.tasks.edit', $task) }}" class="btn btn-primary btn-sm">Изменить</a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success border-0 shadow-sm py-2">{{ session('success') }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">Описание</div>
            <div class="card-body" style="white-space: pre-wrap;">{{ $task->description ?: '—' }}</div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <p class="mb-2"><strong>Сотрудник:</strong><br>{{ $task->employee?->full_name ?? '—' }}</p>
                @if($task->employee?->position)
                    <p class="mb-2 small text-muted">{{ $task->employee->position }}{{ $task->employee->store?->name ? ' · '.$task->employee->store->name : '' }}</p>
                @endif
                <p class="mb-2"><strong>Начало:</strong> {{ $task->starts_at?->format('d.m.Y') ?? '—' }}</p>
                <p class="mb-2"><strong>План окончания:</strong> {{ $task->due_at?->format('d.m.Y') ?? '—' }}</p>
                <p class="mb-2"><strong>Создал:</strong> {{ $task->creator?->name ?? '—' }}</p>
                <p class="mb-0 small text-muted">Создано: {{ $task->created_at?->format('d.m.Y H:i') }}</p>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white">Сменить статус</div>
            <div class="card-body d-flex flex-wrap gap-2">
                @foreach($statuses as $status => $label)
                    @if($status !== $task->status)
                        <form method="post" action="{{ route('management.tasks.status', $task) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $status }}">
                            <input type="hidden" name="redirect" value="show">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $label }}</button>
                        </form>
                    @endif
                @endforeach
            </div>
        </div>

        <form method="post" action="{{ route('management.tasks.destroy', $task) }}" onsubmit="return confirm('Удалить задачу?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">Удалить задачу</button>
        </form>
    </div>
</div>
@endsection
