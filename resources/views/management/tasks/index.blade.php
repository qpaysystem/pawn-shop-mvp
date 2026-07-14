@extends('layouts.app')

@section('title', 'Журнал задач')

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item"><a href="{{ route('section.management') }}">Управление</a></li>
            <li class="breadcrumb-item active" aria-current="page">Задачи</li>
        </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-check2-square me-2"></i>Журнал задач</h1>
            <p class="text-muted small mb-0">Постановка и контроль задач сотрудникам.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('management.tasks.board') }}" class="btn btn-outline-primary">
                <i class="bi bi-kanban"></i> Канбан
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
            <div class="col-md-3">
                <label class="form-label small mb-1">Поиск</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="Название, описание…">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Сотрудник</label>
                <select name="employee_id" class="form-select form-select-sm">
                    <option value="">Все</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}" @selected((int) ($filters['employee_id'] ?? 0) === $employee->id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Статус</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Все</option>
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Начало с</label>
                <input type="date" name="starts_from" class="form-control form-control-sm" value="{{ $filters['starts_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Начало по</label>
                <input type="date" name="starts_to" class="form-control form-control-sm" value="{{ $filters['starts_to'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">План с</label>
                <input type="date" name="due_from" class="form-control form-control-sm" value="{{ $filters['due_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">План по</label>
                <input type="date" name="due_to" class="form-control form-control-sm" value="{{ $filters['due_to'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="overdue" value="1" id="overdue" @checked(!empty($filters['overdue']))>
                    <label class="form-check-label small" for="overdue">Только просроченные</label>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary">Применить</button>
                <a href="{{ route('management.tasks.index') }}" class="btn btn-sm btn-outline-secondary">Сбросить</a>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Задача</th>
                        <th>Сотрудник</th>
                        <th>Статус</th>
                        <th>Начало</th>
                        <th>План окончания</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        <tr class="{{ $task->isOverdue() ? 'table-warning' : '' }}">
                            <td>
                                <a href="{{ route('management.tasks.show', $task) }}" class="fw-semibold text-decoration-none">{{ $task->title }}</a>
                                @if($task->description)
                                    <div class="small text-muted text-truncate" style="max-width:320px;">{{ $task->description }}</div>
                                @endif
                            </td>
                            <td>{{ $task->employee?->full_name ?? '—' }}</td>
                            <td><span class="badge {{ $task->statusBadgeClass() }}">{{ $task->statusLabel() }}</span></td>
                            <td class="text-nowrap">{{ $task->starts_at?->format('d.m.Y') ?? '—' }}</td>
                            <td class="text-nowrap">
                                {{ $task->due_at?->format('d.m.Y') ?? '—' }}
                                @if($task->isOverdue())
                                    <span class="badge text-bg-danger ms-1">просрочено</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('management.tasks.edit', $task) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted p-4">
                                Задач пока нет. <a href="{{ route('management.tasks.create') }}">Создайте первую</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tasks->hasPages())
            <div class="p-3 border-top">{{ $tasks->links() }}</div>
        @endif
    </div>
</div>
@endsection
