@extends('layouts.app')

@section('title', $category ? 'Редактировать категорию' : 'Новая категория')

@section('content')
<h1 class="h4 mb-4">{{ $category ? 'Редактировать категорию' : 'Новая категория' }}</h1>
<form method="post" action="{{ $category ? route('kb.categories.update', $category) : route('kb.categories.store') }}">@csrf @if($category) @method('PUT') @endif
    <div class="mb-3"><label class="form-label">Название *</label><input type="text" name="name" class="form-control" value="{{ old('name', $category?->name) }}" required></div>
    <div class="mb-3"><label class="form-label">Slug (URL)</label><input type="text" name="slug" class="form-control" value="{{ old('slug', $category?->slug) }}" placeholder="auto"></div>
    <div class="mb-3"><label class="form-label">Описание</label><textarea name="description" class="form-control" rows="2">{{ old('description', $category?->description) }}</textarea></div>
    <div class="mb-3"><label class="form-label">Порядок</label><input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $category?->sort_order ?? 0) }}" min="0"></div>
    <div class="mb-3 form-check"><input type="checkbox" name="is_published" class="form-check-input" value="1" {{ old('is_published', $category?->is_published ?? true) ? 'checked' : '' }}><label class="form-check-label">Опубликована</label></div>
    <button type="submit" class="btn btn-primary">{{ $category ? 'Сохранить' : 'Создать' }}</button>
    <a href="{{ route('kb.categories.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
