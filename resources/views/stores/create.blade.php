@extends('layouts.app')

@section('title', 'Новый магазин')

@section('content')
<h1 class="h4 mb-4">Новый магазин</h1>
<form method="post" action="{{ route('stores.store') }}">
    @csrf
    <div class="mb-3"><label class="form-label">Название *</label><input type="text" name="name" class="form-control" value="{{ old('name') }}" required></div>
    <div class="mb-3"><label class="form-label">Адрес</label><input type="text" name="address" class="form-control" value="{{ old('address') }}"></div>
    <div class="mb-3"><label class="form-label">Телефон</label><input type="text" name="phone" class="form-control" value="{{ old('phone') }}"></div>
    <div class="mb-3 form-check"><input type="checkbox" name="is_active" class="form-check-input" value="1" {{ old('is_active', true) ? 'checked' : '' }}><label class="form-check-label">Активен</label></div>
    <button type="submit" class="btn btn-primary">Создать</button>
    <a href="{{ route('stores.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
