@extends('layouts.app')

@section('title', 'Канбан задач')

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item"><a href="{{ route('section.management') }}">Управление</a></li>
            <li class="breadcrumb-item"><a href="{{ route('management.tasks.index') }}">Задачи</a></li>
            <li class="breadcrumb-item active" aria-current="page">Канбан</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-kanban me-2"></i>Канбан задач</h1>
            <p class="text-muted small mb-0">Перемещайте задачи по статусам кнопками на карточке.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('management.tasks.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-list-ul"></i> Журнал
            </a>
            <a href="{{ route('management.tasks.create') }}" class="btn btn-primary">
                <i class="bi bi-plus"></i> Новая задача
            </a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success border-0 shadow-sm py-2">{{ session('success') }}</div>
@endif

<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Сотрудник</label>
                <select name="employee_id" class="form-select form-select-sm">
                    <option value="">Все</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((int) ($filters['employee_id'] ?? 0) === $employee->id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-sm btn-primary">Применить</button>
                <a href="{{ route('management.tasks.board') }}" class="btn btn-sm btn-outline-secondary">Сбросить</a>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 align-items-start">
    @foreach($columns as $status => $tasks)
        <div class="col-12 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">{{ $statuses[$status] ?? $status }}</span>
                    <span class="badge text-bg-light text-dark">{{ $tasks->count() }}</span>
                </div>
                <div class="card-body p-2" style="min-height:280px; background:#f8f9fa;">
                    @forelse($tasks as $task)
                        <div class="card mb-2 border {{ $task->isOverdue() ? 'border-danger' : '' }}">
                            <div class="card-body p-2">
                                <a href="{{ route('management.tasks.show', $task) }}" class="fw-semibold text-decoration-none d-block mb-1">{{ $task->title }}</a>
                                <div class="small text-muted mb-2">{{ $task->employee?->full_name ?? '—' }}</div>
                                <div class="small mb-2">
                                    <i class="bi bi-calendar-event"></i>
                                    {{ $task->starts_at?->format('d.m') ?? '—' }}
                                    →
                                    <span class="{{ $task->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $task->due_at?->format('d.m.Y') ?? '—' }}</span>
                                </div>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($statuses as $nextStatus => $label)
                                        @if($nextStatus !== $status)
                                            <form method="post" action="{{ route('management.tasks.status', $task) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $nextStatus }}">
                                                <input type="hidden" name="redirect" value="board">
                                                @if(!empty($filters['employee_id']))
                                                    <input type="hidden" name="employee_id" value="{{ $filters['employee_id'] }}">
                                                @endif
                                                <button type="submit" class="btn btn-outline-secondary btn-sm py-0 px-1" style="font-size:11px;" title="Перевести в «{{ $label }}»">
                                                    → {{ $label }}
                                                </button>
                                            </form>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-muted small text-center py-4">Пусто</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection
