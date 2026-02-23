@extends('landing.lombard.layout')

@section('title', 'Главная')

@section('content')
{{-- Hero — как на 5.128.186.3, синий фон, белый текст --}}
<section class="py-5" style="padding-top: 60px !important;">
    <div class="container-capital py-5">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h1 class="text-white fw-bold mb-4" style="font-size: 2.5rem;">Займы под залог без отказов</h1>
                <p class="text-white mb-4" style="font-size: 1.125rem; opacity: 0.95;">Быстрая оценка, выгодные условия и сохранность ваших вещей. Деньги в день обращения.</p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="{{ route('login') }}" class="btn">Вход для сотрудников</a>
                    <a href="#contact" class="btn btn_blue">Связаться с нами</a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Услуги --}}
<section id="services" class="py-5">
    <div class="container-capital">
        <h2 class="text-white fw-bold mb-5" style="font-size: 1.75rem;">Наши услуги</h2>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="rounded-3 p-4" style="background: rgba(255,255,255,0.08); min-height: 180px;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-white" style="width: 56px; height: 56px; background: rgba(255,255,255,0.15);">
                        <i class="bi bi-gem fs-4"></i>
                    </div>
                    <h5 class="text-white fw-bold mb-2">Ювелирные изделия</h5>
                    <p class="text-white mb-0" style="font-size: 14px; opacity: 0.9;">Золото, серебро, драгоценные камни. Оценка за 15 минут.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="rounded-3 p-4" style="background: rgba(255,255,255,0.08); min-height: 180px;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-white" style="width: 56px; height: 56px; background: rgba(255,255,255,0.15);">
                        <i class="bi bi-laptop fs-4"></i>
                    </div>
                    <h5 class="text-white fw-bold mb-2">Техника</h5>
                    <p class="text-white mb-0" style="font-size: 14px; opacity: 0.9;">Ноутбуки, телефоны, планшеты, бытовая техника.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="rounded-3 p-4" style="background: rgba(255,255,255,0.08); min-height: 180px;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-white" style="width: 56px; height: 56px; background: rgba(255,255,255,0.15);">
                        <i class="bi bi-clock-history fs-4"></i>
                    </div>
                    <h5 class="text-white fw-bold mb-2">Часы</h5>
                    <p class="text-white mb-0" style="font-size: 14px; opacity: 0.9;">Швейцарские и премиальные часы. Честная оценка.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="rounded-3 p-4" style="background: rgba(255,255,255,0.08); min-height: 180px;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-white" style="width: 56px; height: 56px; background: rgba(255,255,255,0.15);">
                        <i class="bi bi-box-seam fs-4"></i>
                    </div>
                    <h5 class="text-white fw-bold mb-2">Другие ценности</h5>
                    <p class="text-white mb-0" style="font-size: 14px; opacity: 0.9;">Антиквариат, меха, инструменты. Уточняйте по телефону.</p>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Как это работает --}}
<section id="how" class="py-5">
    <div class="container-capital">
        <h2 class="text-white fw-bold mb-5" style="font-size: 1.75rem;">Как это работает</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="d-flex">
                    <span class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center fw-bold me-3 text-white" style="width: 48px; height: 48px; background: rgba(249,186,34,0.3);">1</span>
                    <div>
                        <h5 class="text-white fw-bold mb-2">Принесите вещь</h5>
                        <p class="text-white mb-0" style="font-size: 14px; opacity: 0.9;">Приходите с паспортом и вещью, которую хотите сдать в залог.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex">
                    <span class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center fw-bold me-3 text-white" style="width: 48px; height: 48px; background: rgba(249,186,34,0.3);">2</span>
                    <div>
                        <h5 class="text-white fw-bold mb-2">Оценка и договор</h5>
                        <p class="text-white mb-0" style="font-size: 14px; opacity: 0.9;">Эксперт оценивает залог. Заключаем договор залога, вы получаете деньги.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex">
                    <span class="flex-shrink-0 rounded-circle d-flex align-items-center justify-content-center fw-bold me-3 text-white" style="width: 48px; height: 48px; background: rgba(249,186,34,0.3);">3</span>
                    <div>
                        <h5 class="text-white fw-bold mb-2">Выкуп в удобный срок</h5>
                        <p class="text-white mb-0" style="font-size: 14px; opacity: 0.9;">Погашаете займ и проценты — залог возвращается. Сроки гибкие.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Преимущества — список как на оригинале --}}
<section class="py-5">
    <div class="container-capital">
        <h2 class="text-white fw-bold mb-4" style="font-size: 1.75rem;">Почему мы</h2>
        <ul class="list">
            <li class="text-white">Деньги в день обращения</li>
            <li class="text-white">Честная оценка</li>
            <li class="text-white">Безопасное хранение</li>
            <li class="text-white">Удобный учёт</li>
        </ul>
    </div>
</section>

{{-- Регламентные документы и база знаний --}}
<section id="documents" class="py-5">
    <div class="container-capital">
        <h2 class="text-white fw-bold mb-5" style="font-size: 1.75rem;">Регламентные документы и база знаний</h2>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="rounded-3 p-4" style="background: rgba(255,255,255,0.08); min-height: 160px;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-white" style="width: 56px; height: 56px; background: rgba(255,255,255,0.15);">
                        <i class="bi bi-file-earmark-text fs-4"></i>
                    </div>
                    <h5 class="text-white fw-bold mb-2">Регламентные документы</h5>
                    <p class="text-white mb-0" style="font-size: 14px; opacity: 0.9;">Внутренние регламенты, инструкции и нормативные документы компании. Доступны сотрудникам в корпоративном портале.</p>
                </div>
            </div>
            <div class="col-md-6">
                <a href="{{ route('kb.index') }}" class="text-decoration-none d-block h-100">
                    <div class="rounded-3 p-4 h-100" style="background: rgba(255,255,255,0.08); min-height: 160px; transition: background 0.2s;">
                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 text-white" style="width: 56px; height: 56px; background: rgba(255,255,255,0.15);">
                            <i class="bi bi-journal-bookmark-fill fs-4"></i>
                        </div>
                        <h5 class="text-white fw-bold mb-2">База знаний</h5>
                        <p class="text-white mb-0" style="font-size: 14px; opacity: 0.9;">Справочные материалы, ответы на частые вопросы и инструкции по работе. Открывается без входа.</p>
                    </div>
                </a>
            </div>
        </div>
        <p class="text-white mt-4 mb-0" style="font-size: 0.9375rem; opacity: 0.9;">База знаний доступна всем. Регламентные документы — после входа в корпоративный портал.</p>
        <div class="d-flex flex-wrap gap-3 mt-3">
            <a href="{{ route('kb.index') }}" class="btn btn_blue"><i class="bi bi-journal-bookmark me-1"></i> Открыть базу знаний</a>
            <a href="{{ route('login') }}" class="btn">Вход для сотрудников</a>
        </div>
    </div>
</section>

{{-- Контакты --}}
<section id="contact" class="py-5" style="padding-bottom: 100px !important;">
    <div class="container-capital text-center">
        <h2 class="text-white fw-bold mb-4" style="font-size: 1.75rem;">Контакты</h2>
        <p class="text-white mb-2" style="font-size: 1.125rem; opacity: 0.95;">Звоните или войдите в корпоративный портал</p>
        <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('services.lombard.phone')) }}" class="text-white fw-bold d-inline-block mb-4" style="font-size: 1.5rem;">{{ config('services.lombard.phone') }}</a>
        <div class="mt-3">
            <a href="{{ route('login') }}" class="btn">Вход для сотрудников</a>
        </div>
    </div>
</section>
@endsection
