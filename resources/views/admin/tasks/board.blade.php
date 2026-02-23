@extends('layouts.admin')
@section('title', 'Канбан-доска')
@section('content')
@push('styles')
<style>
.kanban-col-pastel.in_development { background: #e3f2fd; }
.kanban-col-pastel.processing { background: #fff3e0; }
.kanban-col-pastel.execution { background: #e8f5e9; }
.kanban-col-pastel.completed { background: #f3e5f5; }
.kanban-col-pastel .card-header { border-bottom-color: rgba(0,0,0,.06); }
</style>
@endpush
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Канбан-доска задач</h1>
    <a href="{{ route('admin.tasks.index') }}" class="btn btn-outline-secondary">Список задач</a>
</div>

<div class="row g-3" id="kanban-board">
    @foreach($statuses as $status)
    @php $label = \App\Models\Task::statusLabels()[$status]; $items = $tasksByStatus[$status] ?? []; @endphp
    <div class="col-md-3">
        <div class="card h-100 kanban-col-pastel {{ $status }}">
            <div class="card-header py-2">
                <strong>{{ $label }}</strong>
                <span class="badge bg-secondary">{{ count($items) }}</span>
            </div>
            <div class="card-body p-2 min-vh-100" style="min-height: 300px;">
                @foreach($items as $task)
                <div class="card mb-2 shadow-sm">
                    <div class="card-body py-2 px-3">
                        <div class="fw-semibold small">{{ Str::limit($task->title, 40) }}</div>
                        @if($task->client)
                            <div class="small text-muted mt-1"><i class="bi bi-person"></i> {{ $task->client->full_name }}</div>
                        @endif
                        @if($task->due_date)
                            <div class="small text-muted mt-1"><i class="bi bi-calendar-event"></i> Окончание: {{ $task->due_date->format('d.m.Y') }}</div>
                        @endif
                        @if($task->description)
                            <div class="text-muted small mt-1">{{ Str::limit($task->description, 60) }}</div>
                        @endif
                        <div class="mt-2">
                            <a href="{{ route('admin.tasks.edit', $task) }}" class="btn btn-sm btn-outline-primary">Изменить</a>
                        </div>
                    </div>
                </div>
                @endforeach
                @if(empty($items))
                    <p class="text-muted small mb-0">Нет задач</p>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
