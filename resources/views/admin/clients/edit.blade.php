@extends('layouts.admin')
@section('title', 'Редактировать: ' . $client->full_name)
@section('content')
<h1 class="h4 mb-4">Редактировать клиента</h1>
<form method="post" action="{{ route('admin.clients.update', $client) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Имя *</label>
            <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $client->first_name) }}" required>
            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Фамилия *</label>
            <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $client->last_name) }}" required>
            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Дата рождения *</label>
            <input type="date" name="birth_date" class="form-control @error('birth_date') is-invalid @enderror" value="{{ old('birth_date', $client->birth_date?->format('Y-m-d')) }}" required>
            @error('birth_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Дата регистрации *</label>
            <input type="date" name="registered_at" class="form-control @error('registered_at') is-invalid @enderror" value="{{ old('registered_at', $client->registered_at?->format('Y-m-d')) }}" required>
            @error('registered_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $client->email) }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Телефон *</label>
            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $client->phone) }}" required>
            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Telegram ID</label>
            <input type="text" name="telegram_id" class="form-control" value="{{ old('telegram_id', $client->telegram_id) }}" placeholder="123456789" inputmode="numeric" pattern="[0-9]*">
            <small class="text-muted">Числовой ID для входа в личный кабинет (узнать: @userinfobot)</small>
        </div>
        <div class="col-md-6">
            <label class="form-label">Telegram username</label>
            <input type="text" name="telegram_username" class="form-control" value="{{ old('telegram_username', $client->telegram_username) }}" placeholder="@username или username">
            <small class="text-muted">Для справки, без @</small>
        </div>
        <div class="col-md-6">
            <label class="form-label">Пароль для личного кабинета</label>
            <input type="password" name="cabinet_password" class="form-control" placeholder="Оставить пустым — не менять. Или введите новый пароль (мин. 4 символа)" autocomplete="new-password">
            <small class="text-muted">Клиент входит по паролю, если не использует Telegram. Если не задан — по умолчанию 123.</small>
        </div>
        <div class="col-12">
            <label class="form-label">Статус</label>
            <select name="status" class="form-select">
                <option value="active" {{ old('status', $client->status) === 'active' ? 'selected' : '' }}>Активный</option>
                <option value="inactive" {{ old('status', $client->status) === 'inactive' ? 'selected' : '' }}>Неактивный</option>
            </select>
        </div>
        @foreach($customFields as $f)
            @php $value = old('custom_'.$f->name) ?? $client->getCustomFieldValue($f->name); @endphp
            <div class="col-12">@include('admin.clients._custom_field', ['field' => $f, 'value' => $value])</div>
        @endforeach
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-primary">Сохранить</button>
        <a href="{{ route('admin.clients.show', $client) }}" class="btn btn-secondary">Отмена</a>
    </div>
</form>
@endsection
