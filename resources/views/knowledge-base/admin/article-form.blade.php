@extends('layouts.app')

@section('title', $article ? 'Редактировать статью' : 'Новая статья')

@section('content')
<h1 class="h4 mb-4">{{ $article ? 'Редактировать статью' : 'Новая статья' }}</h1>
<form method="post" action="{{ $article ? route('kb.articles.update', $article) : route('kb.articles.store') }}" enctype="multipart/form-data">
    @csrf
    @if($article) @method('PUT') @endif
    <div class="mb-3">
        <label class="form-label">Категория *</label>
        <select name="category_id" class="form-select" required>
            @foreach($categories as $c)
                <option value="{{ $c->id }}" {{ old('category_id', $article?->category_id) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Название *</label>
        <input type="text" name="title" class="form-control" value="{{ old('title', $article?->title) }}" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Slug (URL)</label>
        <input type="text" name="slug" class="form-control" value="{{ old('slug', $article?->slug) }}" placeholder="auto">
    </div>
    <div class="mb-3">
        <label class="form-label">Содержание</label>
        <textarea name="content" class="form-control" rows="12">{{ old('content', $article?->content) }}</textarea>
    </div>

    <div class="mb-3">
        <label class="form-label">Фото</label>
        @if($article && is_array($article->images) && count($article->images) > 0)
            <div class="d-flex flex-wrap gap-2 mb-2">
                @foreach($article->images as $path)
                    @if(is_string($path) && $path !== '')
                    <div class="position-relative border rounded overflow-hidden" style="width: 120px;">
                        <img src="{{ $article->imageUrl($path) }}" alt="" class="img-fluid" style="height: 80px; object-fit: cover; width: 100%;">
                        <label class="position-absolute bottom-0 start-0 end-0 bg-dark bg-opacity-75 text-white small px-1 py-1 m-0 d-flex align-items-center" style="cursor: pointer;">
                            <input type="checkbox" name="remove_images[]" value="{{ $path }}" class="me-1"> Удалить
                        </label>
                    </div>
                    @endif
                @endforeach
            </div>
            <p class="text-muted small">Отметьте «Удалить» и сохраните статью. Ниже — добавить ещё фото (по одному или несколько в форме).</p>
        @endif
        <p class="text-muted small mb-2">Выберите файлы ниже и нажмите «Сохранить» — они добавятся к списку.</p>
        <input type="file" name="images[]" class="form-control" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" multiple>
        <small class="text-muted">JPEG, PNG, GIF, WebP, до 5 МБ. При создании статьи — выберите файлы здесь и нажмите «Создать».</small>
    </div>

    <div class="mb-3">
        <label class="form-label">Ссылки на видео</label>
        <textarea name="video_urls_text" class="form-control" rows="3" placeholder="Одна ссылка на строку&#10;https://www.youtube.com/watch?v=...&#10;https://vimeo.com/...">{{ old('video_urls_text', $article && is_array($article->video_urls) ? implode("\n", $article->video_urls) : '') }}</textarea>
        <small class="text-muted">YouTube, Vimeo или любая другая ссылка на видео. Одна URL на строку.</small>
    </div>

    <div class="mb-3">
        <label class="form-label">Порядок</label>
        <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $article?->sort_order ?? 0) }}" min="0">
    </div>
    <div class="mb-3 form-check">
        <input type="hidden" name="is_published" value="0">
        <input type="checkbox" name="is_published" class="form-check-input" value="1" {{ old('is_published', $article?->is_published ?? true) ? 'checked' : '' }}>
        <label class="form-check-label">Опубликована</label>
    </div>
    <button type="submit" class="btn btn-primary">{{ $article ? 'Сохранить' : 'Создать' }}</button>
    <a href="{{ route('kb.articles.index') }}" class="btn btn-secondary">Отмена</a>
</form>

@if($article)
<div class="mt-4 pt-3 border-top">
    <p class="text-muted small mb-2">Либо загрузить одно фото отдельно:</p>
    <form method="post" action="{{ route('kb.articles.photo.store', $article) }}" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
        @csrf
        <input type="file" name="photo" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" class="form-control form-control-sm w-auto">
        <button type="submit" class="btn btn-primary btn-sm">Загрузить фото</button>
    </form>
</div>
@endif
@endsection
