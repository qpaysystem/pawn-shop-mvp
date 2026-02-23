@extends('landing.lombard.layout')

@section('title', 'О компании')

@section('content')
<div class="container-capital py-5">
    @include('landing.lombard.partials.breadcrumb', ['items' => [['Главная', route('home')], ['О компании']]])
    <h1 class="text-white fw-bold mb-4" style="font-size: 2rem;">О компании</h1>
    <p class="text-white mb-0" style="opacity: 0.95;">{{ config('services.lombard.name', 'Капитал') }} — надёжный ломбард с выгодными условиями залога и честной оценкой. Работаем с ювелирными изделиями, техникой, часами и другими ценными вещами.</p>
</div>
@endsection
