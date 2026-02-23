@extends('layouts.app')

@section('title', 'Редактировать магазин')

@section('content')
<h1 class="h4 mb-4">Редактировать магазин</h1>
<form method="post" action="{{ route('stores.update', $store) }}">
    @csrf @method('PUT')
    <div class="mb-3"><label class="form-label">Название *</label><input type="text" name="name" class="form-control" value="{{ old('name', $store->name) }}" required></div>
    <div class="mb-3"><label class="form-label">Адрес</label><input type="text" name="address" class="form-control" value="{{ old('address', $store->address) }}"></div>
    <div class="mb-3"><label class="form-label">Телефон</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $store->phone) }}"></div>
    <div class="mb-3 form-check"><input type="checkbox" name="is_active" class="form-check-input" value="1" {{ old('is_active', $store->is_active) ? 'checked' : '' }}><label class="form-check-label">Активен</label></div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="{{ route('stores.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
