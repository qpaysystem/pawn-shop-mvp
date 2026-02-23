@extends('cabinet.layout')
@section('title', 'Канбан-доска')
@section('content')
<h1 class="h4 mb-4">Канбан-доска</h1>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <a href="{{ route('cabinet.tasks.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Создать задачу</a>
            <form method="get" action="{{ route('cabinet.board') }}" class="d-flex align-items-center gap-2 flex-wrap">
                <label for="filter-responsible" class="form-label mb-0 fw-medium">Фильтр по ответственному:</label>
                <select id="filter-responsible" name="responsible_id" class="form-select form-select-sm" style="min-width: 200px;" onchange="this.form.submit()">
                    <option value="">— Все —</option>
                    @foreach($clients ?? [] as $c)
                        <option value="{{ $c->id }}" @selected(($filterResponsibleId ?? '') == $c->id || ($filterResponsibleId ?? '') == (string)$c->id)>{{ $c->first_name }} {{ $c->last_name }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Применить</button></noscript>
            </form>
        </div>
    </div>
</div>
<p class="text-muted small">Перетащите задачу в другой столбец, чтобы изменить статус.</p>

<div class="row g-3 kanban-board" data-csrf="{{ csrf_token() }}" data-status-url-base="{{ url('/cabinet/tasks') }}">
    @foreach($statuses as $status)
    @php $label = \App\Models\Task::statusLabels()[$status]; $items = $tasksByStatus[$status] ?? []; @endphp
    <div class="col-md-3 kanban-col" data-status="{{ $status }}">
        <div class="card h-100 border-0 shadow-sm kanban-col-pastel kanban-pastel-{{ $status }}">
            <div class="card-header py-2 border-0">
                <strong>{{ $label }}</strong>
                <span class="badge kanban-col-count">{{ count($items) }}</span>
            </div>
            <div class="card-body p-2 kanban-drop-zone" style="min-height: 280px;">
                @foreach($items as $task)
                <div class="card mb-2 bg-white border kanban-task" draggable="true" data-task-id="{{ $task->id }}">
                    <div class="card-body py-2 px-3">
                        <div class="fw-semibold">{{ Str::limit($task->title, 50) }}</div>
                        @if($task->client)
                            <div class="small text-primary mt-1"><i class="bi bi-person me-1"></i>{{ $task->client->full_name }}</div>
                        @endif
                        @if($task->due_date)
                            <div class="small text-muted mt-1"><i class="bi bi-calendar-event me-1"></i>Окончание: {{ $task->due_date->format('d.m.Y') }}</div>
                        @endif
                        @if($task->description)
                            <div class="text-muted small mt-1">{{ Str::limit($task->description, 80) }}</div>
                        @endif
                    </div>
                </div>
                @endforeach
                @if(empty($items))
                    <p class="text-muted small mb-0 kanban-empty-msg">Нет задач</p>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

@push('styles')
<style>
.kanban-col-pastel.kanban-pastel-in_development { background: #e3f2fd; }
.kanban-col-pastel.kanban-pastel-processing { background: #fff3e0; }
.kanban-col-pastel.kanban-pastel-execution { background: #e8f5e9; }
.kanban-col-pastel.kanban-pastel-completed { background: #f3e5f5; }
.kanban-col-pastel .card-header .badge { background: rgba(0,0,0,.12) !important; color: #333; }
.kanban-task { cursor: grab; }
.kanban-task:active { cursor: grabbing; }
.kanban-task.kanban-dragging { opacity: 0.6; }
.kanban-drop-zone.kanban-drag-over { background: rgba(13, 110, 253, 0.12); border-radius: 0.25rem; min-height: 280px; }
</style>
@endpush
@push('scripts')
<script>
(function() {
    var board = document.querySelector('.kanban-board');
    var csrf = board && board.dataset.csrf;
    var statusUrlBase = board && board.dataset.statusUrlBase;
    if (!board || !statusUrlBase) return;

    var draggedCard = null;
    var sourceColumn = null;

    board.querySelectorAll('.kanban-task').forEach(function(el) {
        el.addEventListener('dragstart', onDragStart);
        el.addEventListener('dragend', onDragEnd);
    });
    board.querySelectorAll('.kanban-drop-zone').forEach(function(zone) {
        zone.addEventListener('dragover', onDragOver);
        zone.addEventListener('dragleave', onDragLeave);
        zone.addEventListener('drop', onDrop);
    });

    function onDragStart(e) {
        draggedCard = e.target.closest('.kanban-task');
        sourceColumn = draggedCard && draggedCard.closest('.kanban-col');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', draggedCard ? draggedCard.dataset.taskId : '');
        if (draggedCard) draggedCard.classList.add('kanban-dragging');
        e.dataTransfer.setDragImage(draggedCard, 0, 0);
    }

    function onDragEnd(e) {
        if (draggedCard) draggedCard.classList.remove('kanban-dragging');
        board.querySelectorAll('.kanban-drop-zone').forEach(function(z) { z.classList.remove('kanban-drag-over'); });
        draggedCard = null;
        sourceColumn = null;
    }

    function onDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        var zone = e.currentTarget;
        zone.classList.add('kanban-drag-over');
    }

    function onDragLeave(e) {
        e.currentTarget.classList.remove('kanban-drag-over');
    }

    function onDrop(e) {
        e.preventDefault();
        var zone = e.currentTarget;
        zone.classList.remove('kanban-drag-over');
        var taskId = e.dataTransfer.getData('text/plain');
        var col = zone.closest('.kanban-col');
        var newStatus = col && col.dataset.status;
        if (!taskId || !newStatus || !draggedCard) return;
        if (sourceColumn && col === sourceColumn) return;

        var url = statusUrlBase + '/' + taskId + '/status';
        fetch(url, {
            method: 'PATCH',
            body: JSON.stringify({ status: newStatus }),
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                if (zone.querySelector('.kanban-empty-msg')) zone.querySelector('.kanban-empty-msg').remove();
                zone.appendChild(draggedCard);
                if (sourceColumn) {
                    var srcZone = sourceColumn.querySelector('.kanban-drop-zone');
                    if (srcZone && srcZone.querySelectorAll('.kanban-task').length === 0) {
                        var p = document.createElement('p');
                        p.className = 'text-muted small mb-0 kanban-empty-msg';
                        p.textContent = 'Нет задач';
                        srcZone.appendChild(p);
                    }
                }
                updateColumnCounts();
            }
        })
        .catch(function() {
            alert('Не удалось обновить статус. Обновите страницу.');
        });
    }

    function updateColumnCounts() {
        board.querySelectorAll('.kanban-col').forEach(function(col) {
            var count = col.querySelectorAll('.kanban-task').length;
            var badge = col.querySelector('.kanban-col-count');
            if (badge) badge.textContent = count;
        });
    }
})();
</script>
@endpush
@endsection
