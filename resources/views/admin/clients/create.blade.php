@extends('layouts.admin')
@section('title', 'Новый клиент')
@section('content')
<h1 class="h4 mb-4">Новый клиент</h1>
<form method="post" action="{{ route('admin.clients.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Имя *</label>
            <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" required>
            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Фамилия *</label>
            <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}" required>
            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Дата рождения *</label>
            <input type="date" name="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date') }}" required>
            @error('birth_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Дата регистрации *</label>
            <input type="date" name="registered_at" class="form-control @error('registered_at') is-invalid @enderror" value="{{ old('registered_at', date('Y-m-d')) }}" required>
            @error('registered_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Телефон *</label>
            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}" required>
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Telegram ID</label>
            <input type="text" name="telegram_id" class="form-control" value="{{ old('telegram_id') }}" placeholder="123456789" inputmode="numeric" pattern="[0-9]*">
            <small class="text-muted">Числовой ID для входа в ЛК (@userinfobot)</small>
        </div>
        <div class="col-md-6">
            <label class="form-label">Telegram username</label>
            <input type="text" name="telegram_username" class="form-control" value="{{ old('telegram_username') }}" placeholder="@username или username">
            <small class="text-muted">Для справки</small>
        </div>
        <div class="col-md-6">
            <label class="form-label">Пароль для личного кабинета</label>
            <input type="password" name="cabinet_password" class="form-control" placeholder="Необязательно. Если пусто — клиент может войти паролем 123" autocomplete="new-password">
        </div>
        <div class="col-12">
            <label class="form-label">Статус</label>
            <select name="status" class="form-select">
                <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Активный</option>
                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Неактивный</option>
            </select>
        </div>
        @foreach($customFields as $f)
        <div class="col-12">
            @include('admin.clients._custom_field', ['field' => $f, 'value' => old('custom_'.$f->name)])
        </div>
        @endforeach
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-primary">Создать</button>
        <a href="{{ route('admin.clients.index') }}" class="btn btn-secondary">Отмена</a>
    </div>
</form>
@endsection
