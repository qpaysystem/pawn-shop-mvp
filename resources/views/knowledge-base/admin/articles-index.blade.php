@extends('layouts.app')

@section('title', 'Статьи базы знаний')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Статьи базы знаний</h1>
    <div>
        <a href="{{ route('kb.categories.index') }}" class="btn btn-outline-secondary btn-sm">Категории</a>
        <a href="{{ route('kb.articles.create') }}" class="btn btn-primary"><i class="bi bi-plus"></i> Статья</a>
    </div>
</div>
<form method="get" class="mb-3">
    <select name="category_id" class="form-select form-select-sm w-auto d-inline-block" onchange="this.form.submit()">
        <option value="">Все категории</option>
        @foreach($categories as $c)<option value="{{ $c->id }}" {{ request('category_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>@endforeach
    </select>
</form>
<table class="table table-hover">
    <thead><tr><th>Название</th><th>Категория</th><th>Опубликована</th><th></th></tr></thead>
    <tbody>
        @foreach($articles as $a)
        <tr>
            <td>{{ $a->title }}</td>
            <td>{{ $a->category->name }}</td>
            <td>@if($a->is_published)<span class="badge bg-success">Да</span>@else<span class="badge bg-secondary">Нет</span>@endif</td>
            <td>
                <a href="{{ route('kb.show', [$a->category->slug, $a->slug]) }}" class="btn btn-sm btn-outline-primary" target="_blank">Открыть</a>
                <a href="{{ route('kb.articles.edit', $a) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                <form action="{{ route('kb.articles.destroy', $a) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить статью?')">@csrf @method('DELETE')<button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button></form>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $articles->links() }}
<a href="{{ route('kb.index') }}" class="btn btn-secondary mt-3">← В базу знаний</a>
@endsection
