@extends('layouts.app')

@section('title', 'Клиенты')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Клиенты</h1>
    <a href="{{ route('clients.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<form method="get" class="mb-3 row g-2">
    <div class="col-auto"><input type="text" name="search" class="form-control form-control-sm" placeholder="ФИО, телефон, email" value="{{ request('search') }}"></div>
    <div class="col-auto"><select name="blacklist" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Все</option><option value="1" {{ request('blacklist') === '1' ? 'selected' : '' }}>В чёрном списке</option></select></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-secondary">Найти</button></div>
</form>
<table class="table table-hover">
    <thead><tr><th>Клиент</th><th>Тип</th><th>Телефон</th><th>Email</th><th>Чёрный список</th><th></th></tr></thead>
    <tbody>
        @foreach($clients as $c)
        <tr>
            <td><a href="{{ route('clients.show', $c) }}">{{ $c->full_name }}</a></td>
            <td>@if($c->isLegal())<span class="badge bg-secondary">Юр. лицо</span>@else<span class="badge bg-light text-dark">Физ. лицо</span>@endif</td>
            <td>{{ $c->phone }}</td>
            <td>{{ $c->email }}</td>
            <td>@if($c->blacklist_flag)<span class="badge bg-danger">Да</span>@else—@endif</td>
            <td>
                <a href="{{ route('clients.edit', $c) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $clients->links() }}
@endsection
