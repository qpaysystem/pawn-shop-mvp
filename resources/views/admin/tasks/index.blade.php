@extends('layouts.admin')
@section('title', 'Задачи')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Задачи</h1>
    <div>
        <a href="{{ route('admin.tasks.board') }}" class="btn btn-outline-primary me-2"><i class="bi bi-kanban"></i> Канбан-доска</a>
        <a href="{{ route('admin.tasks.create') }}" class="btn btn-primary">Добавить задачу</a>
    </div>
</div>

<form method="get" action="{{ route('admin.tasks.index') }}" class="mb-3">
    <select name="status" class="form-select form-select-sm d-inline-block w-auto">
        <option value="">Все статусы</option>
        @foreach(\App\Models\Task::statusLabels() as $value => $label)
            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-sm btn-secondary">Показать</button>
</form>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Название</th><th>Проект</th><th>Бюджет</th><th>Дата окончания</th><th>Статус</th><th>Ответственный</th><th>На доске</th><th></th></tr></thead>
            <tbody>
                @forelse($tasks as $t)
                <tr>
                    <td>{{ Str::limit($t->title, 60) }}</td>
                    <td>{{ $t->project ? $t->project->name : '—' }}</td>
                    <td>{{ $t->budget !== null ? number_format($t->budget, 2) . ' ' . \App\Models\Setting::get('currency', 'RUB') : '—' }}</td>
                    <td>{{ $t->due_date ? $t->due_date->format('d.m.Y') : '—' }}</td>
                    <td><span class="badge bg-secondary">{{ $t->status_label }}</span></td>
                    <td>{{ $t->client ? $t->client->full_name : '—' }}</td>
                    <td>{{ $t->show_on_board ? 'Да' : 'Нет' }}</td>
                    <td>
                        <a href="{{ route('admin.tasks.edit', $t) }}" class="btn btn-sm btn-outline-primary">Изменить</a>
                        <form method="post" action="{{ route('admin.tasks.destroy', $t) }}" class="d-inline" onsubmit="return confirm('Удалить задачу?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-muted">Нет задач</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tasks->hasPages())
        <div class="card-footer">{{ $tasks->links() }}</div>
    @endif
</div>
@endsection
