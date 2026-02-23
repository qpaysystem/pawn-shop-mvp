@extends('layouts.admin')
@section('title', 'Пользователи')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Пользователи системы</h1>
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Добавить пользователя</a>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>ID</th><th>Имя</th><th>Email</th><th>Роль</th><th></th></tr></thead>
            <tbody>
                @foreach($users as $u)
                <tr>
                    <td>{{ $u->id }}</td>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td><span class="badge bg-secondary">{{ $u->role }}</span></td>
                    <td>
                        <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-outline-primary">Изменить</a>
                        @if($u->id !== auth()->id())
                        <form method="post" action="{{ route('admin.users.destroy', $u) }}" class="d-inline" onsubmit="return confirm('Удалить пользователя?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
