@extends('landing.layout')

@section('title', $project->name)

@section('content')
<div class="container py-4">
    <div class="mb-3">
        <a href="{{ url('/') }}#objects" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> К объектам</a>
    </div>

    {{-- Карточка проекта --}}
    <div class="card card-object mb-5 overflow-visible">
        <div class="row g-0">
            <div class="col-md-5 col-lg-4">
                <div class="img-wrap" style="min-height: 280px;">
                    @if($project->sitePhotos->isNotEmpty())
                    <div id="carousel-project" class="carousel slide h-100" data-bs-ride="carousel">
                        <div class="carousel-inner h-100">
                            @foreach($project->sitePhotos as $index => $photo)
                            <div class="carousel-item h-100 {{ $index === 0 ? 'active' : '' }}">
                                <img src="{{ $photo->url }}" alt="{{ $project->name }}" class="d-block w-100">
                            </div>
                            @endforeach
                        </div>
                        @if($project->sitePhotos->count() > 1)
                        <button class="carousel-control-prev" type="button" data-bs-target="#carousel-project" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carousel-project" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
                        @endif
                    </div>
                    @else
                    <div class="d-flex align-items-center justify-content-center h-100 bg-secondary bg-opacity-25">
                        <i class="bi bi-image text-muted" style="font-size: 4rem;"></i>
                    </div>
                    @endif
                </div>
            </div>
            <div class="col-md-7 col-lg-8">
                <div class="card-body p-4">
                    <h1 class="h4 fw-bold mb-3">{{ $project->name }}</h1>
                    @if($project->site_description)
                    <div class="text-muted mb-3">{!! nl2br(e($project->site_description)) !!}</div>
                    @endif
                    <p class="mb-0">Телефон для связи: <a href="tel:+73832910051" class="fw-semibold">+7 (383) 291-00-51</a></p>
                </div>
            </div>
        </div>
        @if($project->map_embed_url)
        @php
            $mapUrl = trim($project->map_embed_url);
            if (preg_match('/src=["\']([^"\']+)["\']/', $mapUrl, $m)) {
                $mapUrl = $m[1];
            }
            $isEmbedUrl = str_contains(strtolower($mapUrl), 'yandex') || str_contains(strtolower($mapUrl), 'map-widget');
        @endphp
        <div class="project-map-wrap">
            <h6 class="px-4 pt-3 mb-0 text-muted small">Расположение</h6>
            <div class="project-map-iframe-wrap">
                @if($isEmbedUrl)
                <iframe src="{{ $mapUrl }}" allowfullscreen title="Карта"></iframe>
                @else
                <a href="https://yandex.ru/maps/?text={{ urlencode($mapUrl) }}" target="_blank" rel="noopener" class="btn btn-outline-secondary m-3">Показать на карте Яндекса</a>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- Свободные квартиры (карточки как в ЛК) --}}
    <h2 class="section-title mb-4">Свободные квартиры</h2>
    @if($project->apartments->isNotEmpty())
    <div class="row g-3">
        @foreach($project->apartments as $apt)
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                @if($apt->layout_photo_url)
                <img src="{{ $apt->layout_photo_url }}" class="card-img-top" alt="Квартира № {{ $apt->apartment_number }}" style="height: 140px; object-fit: cover;">
                @else
                <div class="bg-light d-flex align-items-center justify-content-center card-img-top" style="height: 140px;"><i class="bi bi-image text-muted fs-1"></i></div>
                @endif
                <div class="card-body">
                    <h6 class="card-title mb-1">Квартира № {{ $apt->apartment_number }}</h6>
                    <span class="badge bg-success">Свободна</span>
                    <p class="small text-muted mb-0 mt-1">
                        @if($apt->floor !== null) Этаж {{ $apt->floor }} · @endif
                        @if($apt->rooms_count) {{ $apt->rooms_count }} комн. · @endif
                        @if($apt->living_area) {{ $apt->living_area }} м² @endif
                    </p>
                    @if($apt->entrance)
                    <p class="small text-muted mb-0">Подъезд {{ $apt->entrance }}</p>
                    @endif
                    <a href="tel:+73832910051" class="btn btn-sm btn-outline-primary mt-2">Узнать подробнее</a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <p class="text-muted">В данном объекте пока нет свободных квартир. Свяжитесь с нами: <a href="tel:+73832910051">+7 (383) 291-00-51</a></p>
    @endif
</div>
@endsection
