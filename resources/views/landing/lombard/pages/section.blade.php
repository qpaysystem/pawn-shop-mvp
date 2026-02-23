@extends('landing.lombard.layout')

@section('title', $title)

@section('content')
<div class="container-capital py-5">
    @include('landing.lombard.partials.breadcrumb', [
        'items' => [
            ['Главная', route('home')],
            ['Каталог', route('landing.catalog')],
            [$categoryName, route($categoryRoute)],
            [$title]
        ]
    ])
    <h1 class="text-white fw-bold mb-4" style="font-size: 2rem;">{{ $title }}</h1>
    <p class="text-white mb-0" style="opacity: 0.95;">Раздел «{{ $title }}». Подробности и условия — по телефону {{ config('services.lombard.phone') }}.</p>
</div>
@endsection
