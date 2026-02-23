@extends('layouts.app')

@section('title', $article->title)

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('kb.index') }}">База знаний</a></li>
        <li class="breadcrumb-item"><a href="{{ route('kb.category', $category->slug) }}">{{ $category->name }}</a></li>
        <li class="breadcrumb-item active">{{ $article->title }}</li>
    </ol>
</nav>
<div class="card">
    <div class="card-body">
        <h1 class="h4 mb-3">{{ $article->title }}</h1>
        @if($article->author)<p class="text-muted small">Автор: {{ $article->author->name }}</p>@endif
        <div class="kb-content mb-0">
            {!! nl2br(e($article->content ?? '')) !!}
        </div>

        @php
            $articleImages = $article->images ?? [];
            $videoUrls = is_array($article->video_urls ?? null) ? $article->video_urls : [];
            $hasMaterials = count($articleImages) > 0 || count($videoUrls) > 0;
        @endphp
        @if($hasMaterials)
            <hr class="my-4">
            <h2 class="h5 mb-3">Методические материалы</h2>

            @if(count($articleImages) > 0)
                <div class="mb-4">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($articleImages as $path)
                            @if(is_string($path) && $path !== '')
                                @php $imgUrl = $article->imageUrl($path); @endphp
                                @if($imgUrl !== '')
                                    <a href="{{ $imgUrl }}" target="_blank" rel="noopener" class="d-block">
                                        <img src="{{ $imgUrl }}" alt="{{ $article->title }}" class="rounded border img-fluid" style="max-height: 200px; object-fit: cover;" loading="lazy">
                                    </a>
                                @endif
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @if(count($videoUrls) > 0)
                <div>
                    <ul class="list-unstyled mb-0">
                        @foreach($videoUrls as $url)
                            <li class="mb-2">
                                @if(preg_match('/youtube\.com\/watch\?v=([\w-]+)|youtu\.be\/([\w-]+)/', $url, $m))
                                    @php $vid = $m[1] ?? $m[2]; @endphp
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-decoration-none">
                                        <i class="bi bi-play-circle-fill text-danger me-1"></i> Смотреть видео на YouTube
                                    </a>
                                @elseif(preg_match('/vimeo\.com\/(\d+)/', $url, $m))
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-decoration-none">
                                        <i class="bi bi-play-circle-fill me-1"></i> Смотреть видео на Vimeo
                                    </a>
                                @else
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-decoration-none">
                                        <i class="bi bi-play-circle me-1"></i> Смотреть видео
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </div>
</div>
<a href="{{ route('kb.category', $category->slug) }}" class="btn btn-secondary mt-3">← К списку статей</a>
@endsection
