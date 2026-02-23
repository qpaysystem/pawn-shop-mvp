@extends('cabinet.layout')
@section('title', 'Профиль')
@section('content')
<h1 class="h4 mb-4">Профиль</h1>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="text-muted small">Имя</label>
                <p class="mb-0">{{ $client->first_name }} {{ $client->last_name }}</p>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">Email</label>
                <p class="mb-0">{{ $client->email }}</p>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">Телефон</label>
                <p class="mb-0">{{ $client->phone }}</p>
            </div>
            <div class="col-md-6">
                <label class="text-muted small">Баланс</label>
                <p class="mb-0">{{ number_format($client->balance, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</p>
            </div>
            @if($client->telegram_username || $client->telegram_id)
            <div class="col-12">
                <label class="text-muted small">Telegram</label>
                <p class="mb-0">
                    @if($client->telegram_username)
                        <a href="https://t.me/{{ $client->telegram_username }}" target="_blank" rel="noopener">@{{ $client->telegram_username }}</a>
                    @endif
                    @if($client->telegram_id)
                        <span class="text-muted small">(ID: {{ $client->telegram_id }})</span>
                    @endif
                </p>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-body">
        <h5 class="h6 mb-3">Сменить пароль входа в личный кабинет</h5>
        @if($errors->has('current_password') || $errors->has('password'))
            <div class="alert alert-danger py-2 mb-3">
                @if($errors->has('current_password'))<div>{{ $errors->first('current_password') }}</div>@endif
                @if($errors->has('password'))<div>{{ $errors->first('password') }}</div>@endif
            </div>
        @endif
        <form method="post" action="{{ route('cabinet.profile.password') }}" class="row g-3">
            @csrf
            <div class="col-12">
                <label class="form-label">Текущий пароль</label>
                <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
            </div>
            <div class="col-12">
                <label class="form-label">Новый пароль</label>
                <input type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password">
            </div>
            <div class="col-12">
                <label class="form-label">Повторите новый пароль</label>
                <input type="password" name="password_confirmation" class="form-control" required minlength="6" autocomplete="new-password">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Сохранить новый пароль</button>
            </div>
        </form>
    </div>
</div>

<p class="text-muted small mt-3 mb-0">Для изменения имени, контактов и других данных обратитесь к администратору.</p>
@endsection
