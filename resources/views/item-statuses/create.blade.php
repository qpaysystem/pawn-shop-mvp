@extends('layouts.app')

@section('title', 'Новый статус')

@section('content')
<h1 class="h4 mb-4">Новый статус товара</h1>
<form method="post" action="{{ route('item-statuses.store') }}">@csrf
    <div class="mb-3"><label class="form-label">Название *</label><input type="text" name="name" class="form-control" value="{{ old('name') }}" required></div>
    <div class="mb-3"><label class="form-label">Цвет (для бейджа)</label><input type="text" name="color" class="form-control" placeholder="#28a745" value="{{ old('color') }}"></div>
    <button type="submit" class="btn btn-primary">Создать</button>
    <a href="{{ route('item-statuses.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
