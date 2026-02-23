@extends('landing.lombard.layout')

@section('title', 'Гаджеты')

@section('content')
<div class="container-capital py-5">
    @include('landing.lombard.partials.breadcrumb', ['items' => [['Главная', route('home')], ['Каталог', route('landing.catalog')], ['Гаджеты']]])
    <h1 class="text-white fw-bold mb-4" style="font-size: 2rem;">Гаджеты</h1>
    <p class="text-white mb-4" style="opacity: 0.95;">Принимаем в залог электронику и гаджеты.</p>
    <ul class="list text-white">
        <li><a href="{{ route('landing.gadjets.section', 'phone') }}" class="text-white">Телефоны</a></li>
        <li><a href="{{ route('landing.gadjets.section', 'comp') }}" class="text-white">Компьютеры</a></li>
        <li><a href="{{ route('landing.gadjets.section', 'play') }}" class="text-white">Плееры</a></li>
        <li><a href="{{ route('landing.gadjets.section', 'photo') }}" class="text-white">Фототехника</a></li>
    </ul>
</div>
@endsection
