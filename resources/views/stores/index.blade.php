@extends('layouts.app')

@section('title', 'Магазины')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Магазины</h1>
    <a href="{{ route('stores.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<table class="table table-hover">
    <thead>
        <tr><th>Название</th><th>Адрес</th><th>Телефон</th><th>Активен</th><th></th></tr>
    </thead>
    <tbody>
        @foreach($stores as $s)
        <tr>
            <td>{{ $s->name }}</td>
            <td>{{ $s->address }}</td>
            <td>{{ $s->phone }}</td>
            <td>@if($s->is_active)<span class="badge bg-success">Да</span>@else<span class="badge bg-secondary">Нет</span>@endif</td>
            <td>
                <a href="{{ route('stores.edit', $s) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                <form action="{{ route('stores.destroy', $s) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $stores->links() }}
@endsection
