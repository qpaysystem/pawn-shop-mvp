@extends('landing.lombard.layout')

@section('title', 'Инструменты')

@section('content')
<div class="container-capital py-5">
    @include('landing.lombard.partials.breadcrumb', ['items' => [['Главная', route('home')], ['Каталог', route('landing.catalog')], ['Инструменты']]])
    <h1 class="text-white fw-bold mb-4" style="font-size: 2rem;">Инструменты</h1>
    <p class="text-white mb-4" style="opacity: 0.95;">Принимаем в залог инструменты.</p>
    <ul class="list text-white">
        <li><a href="{{ route('landing.tool.section', 'shurupoverti') }}" class="text-white">Шуруповёрты</a></li>
        <li><a href="{{ route('landing.tool.section', 'perforatori') }}" class="text-white">Перфораторы</a></li>
        <li><a href="{{ route('landing.tool.section', 'lobziki') }}" class="text-white">Лобзики</a></li>
    </ul>
</div>
@endsection
