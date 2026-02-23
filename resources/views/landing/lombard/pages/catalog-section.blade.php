@extends('landing.lombard.layout')

@section('title', 'Каталог — ' . $category_code)

@section('content')
<div class="container-capital py-5">
    @include('landing.lombard.partials.breadcrumb', [
        'items' => [
            ['Главная', route('home')],
            ['Каталог', route('landing.catalog')],
            [ucfirst($category_code)]
        ]
    ])
    <h1 class="text-white fw-bold mb-4" style="font-size: 2rem;">Раздел: {{ $category_code }}</h1>
    <p class="text-white mb-0" style="opacity: 0.95;">Товары в данной категории. Уточняйте наличие по телефону.</p>
</div>
@endsection
