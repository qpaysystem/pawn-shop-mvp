@extends('layouts.app')

@section('title', 'Пользователи')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Пользователи</h1>
    <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<table class="table table-hover">
    <thead><tr><th>Имя</th><th>Email</th><th>Роль</th><th>Магазин</th><th></th></tr></thead>
    <tbody>
        @foreach($users as $u)
        <tr>
            <td>{{ $u->name }}</td>
            <td>{{ $u->email }}</td>
            <td><span class="badge bg-secondary">{{ $u->role }}</span></td>
            <td>{{ $u->store?->name ?? '—' }}</td>
            <td>
                <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                @if($u->id !== auth()->id())
                <form action="{{ route('users.destroy', $u) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить пользователя?')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button></form>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $users->links() }}
@endsection
