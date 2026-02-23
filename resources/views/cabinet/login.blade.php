@extends('cabinet.layout')
@section('title', 'Вход')
@section('content')
<div class="row justify-content-center g-4">
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-body text-center p-5">
                <h4 class="card-title mb-4">Вход в личный кабинет</h4>
                <p class="text-muted mb-4">Сначала войдите — затем вам будут доступны разделы: <strong>Транзакции</strong>, <strong>Канбан-доска</strong>, <strong>Профиль</strong>.</p>
                <p class="text-muted small mb-4">Войдите через Telegram или по паролю ниже.</p>
                @if($botUsername)
                    <script async src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="{{ $botUsername }}"
                        data-size="large"
                        data-auth-url="{{ url('/cabinet/auth/telegram') }}"
                        data-request-access="write">
                    </script>
                @else
                    <div class="alert alert-warning">
                        Telegram-бот не настроен. Обратитесь к администратору.
                    </div>
                @endif
                <p class="small text-muted mt-4 mb-0">Если ваш аккаунт не привязан к карточке клиента, свяжитесь с нами.</p>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow">
            <div class="card-body p-4">
                <h5 class="card-title mb-3">Вход по паролю</h5>
                <p class="text-muted small mb-3">Выберите себя в списке и введите пароль. Стартовый пароль — дата рождения (ДД.ММ.ГГГГ). После первого входа смените пароль в разделе «Профиль».</p>
                @if($errors->has('password'))
                    <div class="alert alert-danger py-2">{{ $errors->first('password') }}</div>
                @endif
                @if($clients->isEmpty())
                    <div class="alert alert-secondary py-2">Нет активных клиентов. Создайте клиента в админке.</div>
                @else
                <form method="post" action="{{ route('cabinet.password.login') }}">
                    @csrf
                    <div class="mb-3 text-start">
                        <label class="form-label">Клиент</label>
                        <select name="client_id" class="form-select" required>
                            <option value="">— Выберите клиента —</option>
                            @foreach($clients as $c)
                                <option value="{{ $c->id }}" @selected(old('client_id') == $c->id)>{{ $c->first_name }} {{ $c->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label">Пароль</label>
                        <input type="password" name="password" class="form-control" placeholder="Пароль" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Войти</button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
