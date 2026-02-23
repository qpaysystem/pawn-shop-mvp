@extends('layouts.app')

@section('title', 'Категории товаров')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Категории товаров</h1>
    <a href="{{ route('item-categories.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<table class="table table-hover">
    <thead>
        <tr><th>Название</th><th>Родитель</th><th></th></tr>
    </thead>
    <tbody>
        @foreach($categories as $c)
        <tr>
            <td>{{ $c->name }}</td>
            <td>{{ $c->parent?->name ?? '—' }}</td>
            <td>
                <a href="{{ route('item-categories.edit', $c) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                <form action="{{ route('item-categories.destroy', $c) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $categories->links() }}
@endsection
