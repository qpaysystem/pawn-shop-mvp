@extends('layouts.admin')
@section('title', 'Новый пользователь')
@section('content')
<h1 class="h4 mb-4">Новый пользователь</h1>
<form method="post" action="{{ route('admin.users.store') }}">
    @csrf
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Имя *</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Пароль *</label>
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Подтверждение пароля *</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>
        <div class="col-12">
            <label class="form-label">Роль</label>
            <select name="role" class="form-select">
                <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Администратор</option>
                <option value="super_admin" {{ old('role') === 'super_admin' ? 'selected' : '' }}>Супер-админ</option>
            </select>
        </div>
    </div>
    <div class="mt-4">
        <button type="submit" class="btn btn-primary">Создать</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Отмена</a>
    </div>
</form>
@endsection
