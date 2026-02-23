@extends('layouts.app')

@section('title', 'Категории базы знаний')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Категории базы знаний</h1>
    <div>
        <a href="{{ route('kb.articles.index') }}" class="btn btn-outline-secondary btn-sm">Статьи</a>
        <a href="{{ route('kb.categories.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Категория</a>
    </div>
</div>
<table class="table table-hover">
    <thead><tr><th>Название</th><th>Slug</th><th>Статей</th><th>Опубликована</th><th></th></tr></thead>
    <tbody>
        @foreach($categories as $c)
        <tr>
            <td>{{ $c->name }}</td>
            <td><code>{{ $c->slug }}</code></td>
            <td>{{ $c->articles_count }}</td>
            <td>@if($c->is_published)<span class="badge bg-success">Да</span>@else<span class="badge bg-secondary">Нет</span>@endif</td>
            <td>
                <a href="{{ route('kb.categories.edit', $c) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                @if($c->articles_count === 0)
                <form action="{{ route('kb.categories.destroy', $c) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить категорию?')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button></form>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
<a href="{{ route('kb.index') }}" class="btn btn-secondary">← В базу знаний</a>
@endsection
