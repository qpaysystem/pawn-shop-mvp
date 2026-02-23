@extends('layouts.app')

@section('title', 'Статусы товара')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Статусы товара</h1>
    <a href="{{ route('item-statuses.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<table class="table table-hover">
    <thead><tr><th>Название</th><th>Цвет</th><th></th></tr></thead>
    <tbody>
        @foreach($statuses as $s)
        <tr>
            <td>{{ $s->name }}</td>
            <td>@if($s->color)<span class="badge" style="background-color:{{ $s->color }}">{{ $s->color }}</span>@else—@endif</td>
            <td>
                <a href="{{ route('item-statuses.edit', $s) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                <form action="{{ route('item-statuses.destroy', $s) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить?')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button></form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $statuses->links() }}
@endsection
