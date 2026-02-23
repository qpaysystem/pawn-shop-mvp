@extends('landing.lombard.layout')

@section('title', 'Золото')

@section('content')
<div class="container-capital py-5">
    @include('landing.lombard.partials.breadcrumb', ['items' => [['Главная', route('home')], ['Каталог', route('landing.catalog')], ['Золото']]])
    <h1 class="text-white fw-bold mb-4" style="font-size: 2rem;">Золото</h1>
    <p class="text-white mb-4" style="opacity: 0.95;">Принимаем в залог и на комиссию изделия из золота.</p>
    <ul class="list text-white">
        <li><a href="{{ route('landing.gold.section', 'mernie') }}" class="text-white">Мерные изделия</a></li>
        <li><a href="{{ route('landing.gold.section', 'coins') }}" class="text-white">Монеты</a></li>
        <li><a href="{{ route('landing.gold.section', 'rings') }}" class="text-white">Кольца</a></li>
        <li><a href="{{ route('landing.gold.section', 'lom') }}" class="text-white">Лом</a></li>
    </ul>
</div>
@endsection
