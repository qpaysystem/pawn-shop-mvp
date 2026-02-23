@extends('landing.lombard.layout')

@section('title', 'Меха')

@section('content')
<div class="container-capital py-5">
    @include('landing.lombard.partials.breadcrumb', ['items' => [['Главная', route('home')], ['Каталог', route('landing.catalog')], ['Меха']]])
    <h1 class="text-white fw-bold mb-4" style="font-size: 2rem;">Меха</h1>
    <p class="text-white mb-4" style="opacity: 0.95;">Принимаем в залог изделия из меха.</p>
    <ul class="list text-white">
        <li><a href="{{ route('landing.fur.section', 'sobol') }}" class="text-white">Соболь</a></li>
        <li><a href="{{ route('landing.fur.section', 'norka') }}" class="text-white">Норка</a></li>
    </ul>
</div>
@endsection
