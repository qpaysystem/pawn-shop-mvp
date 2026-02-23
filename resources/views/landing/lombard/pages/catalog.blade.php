@extends('landing.lombard.layout')

@section('title', 'Каталог')

@section('content')
<div class="container-capital py-5">
    @include('landing.lombard.partials.breadcrumb', ['items' => [['Главная', route('home')], ['Каталог']]])
    <h1 class="text-white fw-bold mb-4" style="font-size: 2rem;">Каталог</h1>
    <p class="text-white mb-4" style="opacity: 0.95;">Разделы каталога:</p>
    <ul class="list text-white">
        <li><a href="{{ route('landing.gold') }}" class="text-white">Золото</a></li>
        <li><a href="{{ route('landing.fur') }}" class="text-white">Меха</a></li>
        <li><a href="{{ route('landing.technical') }}" class="text-white">Техника</a></li>
        <li><a href="{{ route('landing.tool') }}" class="text-white">Инструменты</a></li>
        <li><a href="{{ route('landing.gadjets') }}" class="text-white">Гаджеты</a></li>
    </ul>
</div>
@endsection
