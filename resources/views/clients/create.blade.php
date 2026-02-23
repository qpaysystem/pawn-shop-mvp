@extends('layouts.app')

@section('title', 'Новый клиент')

@section('content')
<h1 class="h4 mb-4">Новый клиент</h1>
<form method="post" action="{{ route('clients.store') }}">@csrf
    <div class="row mb-3">
        <div class="col-md-4"><label class="form-label">Фамилия *</label><input type="text" name="last_name" class="form-control" value="{{ old('last_name') }}" required></div>
        <div class="col-md-4"><label class="form-label">Имя *</label><input type="text" name="first_name" class="form-control" value="{{ old('first_name') }}" required></div>
        <div class="col-md-4"><label class="form-label">Отчество</label><input type="text" name="patronymic" class="form-control" value="{{ old('patronymic') }}"></div>
    </div>
    <div class="mb-3"><label class="form-label">Телефон *</label><input type="text" name="phone" class="form-control" value="{{ old('phone') }}" required></div>
    <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}"></div>
    <div class="mb-3"><label class="form-label">Паспортные данные</label><textarea name="passport_data" class="form-control" rows="2">{{ old('passport_data') }}</textarea></div>
    <div class="mb-3"><label class="form-label">Заметки</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
    <div class="mb-3 form-check"><input type="checkbox" name="blacklist_flag" class="form-check-input" value="1" {{ old('blacklist_flag') ? 'checked' : '' }}><label class="form-check-label">Чёрный список</label></div>
    <button type="submit" class="btn btn-primary">Создать</button>
    <a href="{{ route('clients.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
