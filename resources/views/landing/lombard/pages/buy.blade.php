@extends('landing.lombard.layout')

@section('title', 'Покупка')

@section('content')
<div class="container-capital py-5">
    @include('landing.lombard.partials.breadcrumb', ['items' => [['Главная', route('home')], ['Покупка']]])
    <h1 class="text-white fw-bold mb-4" style="font-size: 2rem;">Покупка</h1>
    <p class="text-white mb-0" style="opacity: 0.95;">В этом разделе вы можете приобрести товары из нашего ассортимента. Информация о наличии и ценах — по телефону или в офисе.</p>
</div>
@endsection
