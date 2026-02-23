@extends('layouts.app')

@section('title', 'База знаний')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">База знаний</h1>
    @auth
    @if(auth()->user()->hasFullStoreAccess() || auth()->user()->isSuperAdmin())
    <a href="{{ route('kb.categories.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear"></i> Управление</a>
    @endif
    @endauth
</div>
<p class="text-muted">Обучение нового персонала и регламентные документы.</p>
<div class="row g-3">
    @forelse($categories as $cat)
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><a href="{{ route('kb.category', $cat->slug) }}" class="text-decoration-none">{{ $cat->name }}</a></h5>
                @if($cat->description)
                <p class="card-text small text-muted">{{ \Illuminate\Support\Str::limit($cat->description, 120) }}</p>
                @endif
                <p class="mb-0 small">Статей: {{ $cat->articles_count }}</p>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12">
        <div class="alert alert-info">Пока нет категорий. @auth @if(auth()->user()->hasFullStoreAccess() || auth()->user()->isSuperAdmin())<a href="{{ route('kb.categories.create') }}">Создать категорию</a>@endif @endauth</div>
    </div>
    @endforelse
</div>
@endsection
