@extends('layouts.app')

@section('title', 'Бренды')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Бренды</h1>
    <a href="{{ route('brands.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить</a>
</div>
<table class="table table-hover">
    <thead><tr><th>Название</th><th></th></tr></thead>
    <tbody>
        @foreach($brands as $b)
        <tr>
            <td>{{ $b->name }}</td>
            <td>
                <a href="{{ route('brands.edit', $b) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                <form action="{{ route('brands.destroy', $b) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить?')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button></form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $brands->links() }}
@endsection
