@extends('layouts.admin')
@section('title', 'Клиенты')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Клиенты</h1>
    <a href="{{ route('admin.clients.create') }}" class="btn btn-primary">Добавить клиента</a>
</div>
<form method="get" class="row g-2 mb-4">
    <div class="col-auto">
        <input type="text" name="search" class="form-control" placeholder="Поиск..." value="{{ request('search') }}">
    </div>
    <div class="col-auto">
        <select name="status" class="form-select">
            <option value="">Все статусы</option>
            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Активный</option>
            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Неактивный</option>
        </select>
    </div>
    <div class="col-auto"><button type="submit" class="btn btn-secondary">Найти</button></div>
</form>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Имя</th>
                    <th>Email</th>
                    <th>Телефон</th>
                    <th>Баланс</th>
                    <th>Статус</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($clients as $c)
                <tr>
                    <td>{{ $c->id }}</td>
                    <td>{{ $c->full_name }}</td>
                    <td>{{ $c->email }}</td>
                    <td>{{ $c->phone }}</td>
                    <td>{{ number_format($c->balance, 2) }}</td>
                    <td><span class="badge bg-{{ $c->status === 'active' ? 'success' : 'secondary' }}">{{ $c->status === 'active' ? 'Активный' : 'Неактивный' }}</span></td>
                    <td>
                        <a href="{{ route('admin.clients.show', $c) }}" class="btn btn-sm btn-outline-primary">Открыть</a>
                        <a href="{{ route('admin.clients.edit', $c) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-muted">Нет клиентов</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $clients->links() }}</div>
@endsection
