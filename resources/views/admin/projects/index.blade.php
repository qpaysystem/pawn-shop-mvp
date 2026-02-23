@extends('layouts.admin')
@section('title', 'Проекты')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Проекты</h1>
    <a href="{{ route('admin.projects.create') }}" class="btn btn-primary">Создать проект</a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Название</th>
                    <th>Статей расхода</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $p)
                <tr>
                    <td>
                        <a href="{{ route('admin.projects.show', $p) }}" class="fw-medium">{{ $p->name }}</a>
                    </td>
                    <td>{{ $p->expense_items_count }}</td>
                    <td>
                        <a href="{{ route('admin.projects.show', $p) }}" class="btn btn-sm btn-outline-primary">Карточка</a>
                        <a href="{{ route('admin.projects.edit', $p) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                        <form method="post" action="{{ route('admin.projects.destroy', $p) }}" class="d-inline" onsubmit="return confirm('Удалить проект и все статьи расхода?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-muted">Нет проектов. Создайте первый проект.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($projects->hasPages())
        <div class="card-footer">{{ $projects->links() }}</div>
    @endif
</div>
@endsection
