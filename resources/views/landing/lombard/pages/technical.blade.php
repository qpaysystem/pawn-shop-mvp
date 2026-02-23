@extends('landing.lombard.layout')

@section('title', 'Техника')

@section('content')
<div class="container-capital py-5">
    @include('landing.lombard.partials.breadcrumb', ['items' => [['Главная', route('home')], ['Каталог', route('landing.catalog')], ['Техника']]])
    <h1 class="text-white fw-bold mb-4" style="font-size: 2rem;">Техника</h1>
    <p class="text-white mb-4" style="opacity: 0.95;">Принимаем в залог бытовую и прочую технику.</p>
    <ul class="list text-white">
        <li><a href="{{ route('landing.technical.section', 'mv') }}" class="text-white">Музыкальные центры</a></li>
        <li><a href="{{ route('landing.technical.section', 'fr') }}" class="text-white">Холодильники</a></li>
        <li><a href="{{ route('landing.technical.section', 'tv') }}" class="text-white">Телевизоры</a></li>
        <li><a href="{{ route('landing.technical.section', 'st') }}" class="text-white">Станки</a></li>
    </ul>
</div>
@endsection
