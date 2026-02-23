@extends('layouts.app')

@section('title', $category->name)

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('kb.index') }}">База знаний</a></li>
        <li class="breadcrumb-item active">{{ $category->name }}</li>
    </ol>
</nav>
<h1 class="h4 mb-4">{{ $category->name }}</h1>
@if($category->description)<p class="text-muted">{{ $category->description }}</p>@endif
<ul class="list-group list-group-flush">
    @forelse($articles as $article)
    <li class="list-group-item d-flex justify-content-between align-items-center">
        <a href="{{ route('kb.show', [$category->slug, $article->slug]) }}">{{ $article->title }}</a>
    </li>
    @empty
    <li class="list-group-item text-muted">В этой категории пока нет статей.</li>
    @endforelse
</ul>
<a href="{{ route('kb.index') }}" class="btn btn-secondary mt-3">← К списку категорий</a>
@endsection
