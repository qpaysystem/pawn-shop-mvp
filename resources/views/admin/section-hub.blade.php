@extends('layouts.app')

@section('title', $title)

@section('content')
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Дашборд</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
        </ol>
    </nav>
    <h1 class="h3 mb-2">{{ $title }}</h1>
    @if(!empty($intro))
        <p class="text-muted mb-0">{{ $intro }}</p>
    @endif
</div>

@if(count($links) === 0)
    <div class="alert alert-secondary border-0 shadow-sm">
        У вас нет доступа к подразделам этого раздела. Обратитесь к администратору.
    </div>
@else
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
        @foreach($links as $link)
            <div class="col">
                <a href="{{ route($link['route']) }}" class="text-decoration-none section-hub-card-link">
                    <div class="card h-100 border-0 shadow-sm section-hub-card">
                        <div class="card-body d-flex align-items-start">
                            <span class="section-hub-icon rounded-3 d-flex align-items-center justify-content-center flex-shrink-0 me-3">
                                <i class="bi {{ $link['icon'] }}"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="fw-semibold text-dark">{{ $link['label'] }}</div>
                                @if(!empty($link['hint']))
                                    <div class="small text-muted mt-1">{{ $link['hint'] }}</div>
                                @endif
                            </div>
                            <i class="bi bi-chevron-right text-muted ms-auto flex-shrink-0 mt-1"></i>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endif
@endsection
