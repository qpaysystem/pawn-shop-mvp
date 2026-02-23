@extends('layouts.frontend')
@section('title', 'Клиенты')
@section('content')
<h1 class="h4 mb-4">Клиенты</h1>
<form method="get" action="{{ request()->url() }}" class="card card-body mb-4">
    <div class="row g-2">
        <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Поиск по имени или фамилии" value="{{ request('search') }}">
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">Все статусы</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Активный</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Неактивный</option>
            </select>
        </div>
        @foreach($customFields as $f)
            @if(in_array($f->type, ['text', 'number']))
            <div class="col-md-2">
                <input type="{{ $f->type === 'number' ? 'number' : 'text' }}" name="filter_{{ $f->name }}" class="form-control" placeholder="{{ $f->label }}" value="{{ request('filter_'.$f->name) }}">
            </div>
            @endif
        @endforeach
        <div class="col-md-auto">
            <button type="submit" class="btn btn-primary">Найти</button>
            <a href="{{ route('frontend.clients.list') }}" class="btn btn-outline-secondary">Сбросить</a>
        </div>
    </div>
</form>
<div class="row g-3">
    @forelse($clients as $c)
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            @if($c->photo_path)
                <img src="{{ asset('storage/'.$c->photo_path) }}" class="card-img-top" alt="" style="height: 200px; object-fit: cover;">
            @else
                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                    <i class="bi bi-person display-4 text-muted"></i>
                </div>
            @endif
            <div class="card-body">
                <h5 class="card-title">{{ $c->full_name }}</h5>
                <p class="card-text text-muted small mb-1">{{ $c->email }}</p>
                <p class="card-text">Баланс: <strong>{{ number_format($c->balance, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</strong></p>
                <a href="{{ route('frontend.clients.show', $c) }}" class="btn btn-outline-primary btn-sm">Подробнее</a>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12"><p class="text-muted">Клиенты не найдены.</p></div>
    @endforelse
</div>
<div class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="text-muted small">Сортировка:
        <a href="{{ request()->fullUrlWithQuery(['sort' => 'first_name', 'dir' => 'asc']) }}">Имя</a>,
        <a href="{{ request()->fullUrlWithQuery(['sort' => 'balance', 'dir' => 'desc']) }}">Баланс</a>,
        <a href="{{ request()->fullUrlWithQuery(['sort' => 'registered_at', 'dir' => 'desc']) }}">Дата регистрации</a>
    </div>
    <div>{{ $clients->links() }}</div>
</div>
@endsection
