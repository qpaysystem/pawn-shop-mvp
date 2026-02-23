@extends('landing.lombard.layout')

@section('title', 'Контакты')

@section('content')
<div class="container-capital py-5">
    @include('landing.lombard.partials.breadcrumb', ['items' => [['Главная', route('home')], ['Контакты']]])
    <h1 class="text-white fw-bold mb-4" style="font-size: 2rem;">Контакты</h1>
    <p class="text-white mb-2" style="opacity: 0.95;">Свяжитесь с нами:</p>
    <p class="text-white mb-0">
        <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('services.lombard.phone')) }}" class="text-white fw-bold" style="font-size: 1.25rem;">{{ config('services.lombard.phone') }}</a>
    </p>
    <p class="text-white mt-4 mb-0"><a href="{{ route('login') }}" class="btn mt-3">Вход для сотрудников</a></p>
</div>
@endsection
