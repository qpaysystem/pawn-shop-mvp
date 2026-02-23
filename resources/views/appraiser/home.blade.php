@extends('layouts.appraiser')

@section('title', 'Оценщик')

@push('styles')
<style>
.appraiser-grid { display: grid; gap: 1rem; grid-template-columns: 1fr; }
@media (min-width: 576px) { .appraiser-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 768px) { .appraiser-grid { grid-template-columns: repeat(3, 1fr); max-width: 900px; } }
.appraiser-card {
    display: block;
    text-decoration: none;
    color: inherit;
    background: #fff;
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border: 1px solid rgba(0,0,0,0.06);
    min-height: 100px;
    transition: transform 0.15s, box-shadow 0.15s;
    -webkit-tap-highlight-color: transparent;
}
.appraiser-card:hover, .appraiser-card:focus { color: inherit; transform: translateY(-2px); box-shadow: 0 4px 14px rgba(0,0,0,0.12); }
.appraiser-card:active { transform: translateY(0); }
.appraiser-card .icon-wrap {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    margin-bottom: 0.75rem;
}
.appraiser-card.appraiser-accept .icon-wrap { background: rgba(34, 77, 102, 0.12); color: var(--lombard-primary); }
.appraiser-card.appraiser-redemption .icon-wrap { background: rgba(25, 135, 84, 0.12); color: #198754; }
.appraiser-card.appraiser-cash .icon-wrap { background: rgba(249, 186, 34, 0.25); color: #b8860b; }
.appraiser-card .h5 { font-size: 1.15rem; font-weight: 600; margin-bottom: 0.25rem; }
.appraiser-card .text-muted { font-size: 0.875rem; }
.appraiser-footer { margin-top: 2rem; }
</style>
@endpush

@section('content')
<h1 class="h4 mb-4">Оценщик / Товаровед</h1>
<p class="text-muted mb-4">Приём товара, выкуп и касса — быстрый доступ.</p>

<div class="appraiser-grid">
    <a href="{{ route('accept.create') }}" class="appraiser-card appraiser-accept">
        <div class="icon-wrap"><i class="bi bi-plus-circle-fill"></i></div>
        <h2 class="h5">Приём товара</h2>
        <p class="text-muted mb-0">Оформление залога, комиссии или скупки</p>
    </a>
    <a href="{{ route('accept.create') }}?purpose=redemption" class="appraiser-card appraiser-redemption">
        <div class="icon-wrap"><i class="bi bi-arrow-repeat"></i></div>
        <h2 class="h5">Выкуп</h2>
        <p class="text-muted mb-0">Поиск договора и оформление выкупа</p>
    </a>
    <a href="{{ route('cash.index') }}" class="appraiser-card appraiser-cash">
        <div class="icon-wrap"><i class="bi bi-cash-stack"></i></div>
        <h2 class="h5">Касса</h2>
        <p class="text-muted mb-0">Операции, отчёты, новый приход/расход</p>
    </a>
</div>

<div class="appraiser-footer">
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Полная версия → Дашборд</a>
</div>
@endsection
