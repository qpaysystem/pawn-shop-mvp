@extends('landing.lombard.layout')

@section('title', 'Товар')

@section('content')
<div class="container-capital py-5">
    @include('landing.lombard.partials.breadcrumb', [
        'items' => [
            ['Главная', route('home')],
            ['Каталог', route('landing.catalog')],
            [$category_code, route('landing.catalog.section', $category_code)],
            ['Товар #' . $id]
        ]
    ])
    <h1 class="text-white fw-bold mb-4" style="font-size: 2rem;">Товар #{{ $id }}</h1>
    <p class="text-white mb-0" style="opacity: 0.95;">Категория: {{ $category_code }}. Подробности — в офисе или по телефону.</p>
</div>
@endsection
