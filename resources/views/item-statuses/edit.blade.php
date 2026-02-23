@extends('layouts.app')

@section('title', 'Редактировать статус')

@section('content')
<h1 class="h4 mb-4">Редактировать статус</h1>
<form method="post" action="{{ route('item-statuses.update', $itemStatus) }}">@csrf @method('PUT')
    <div class="mb-3"><label class="form-label">Название *</label><input type="text" name="name" class="form-control" value="{{ old('name', $itemStatus->name) }}" required></div>
    <div class="mb-3"><label class="form-label">Цвет</label><input type="text" name="color" class="form-control" value="{{ old('color', $itemStatus->color) }}"></div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="{{ route('item-statuses.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
