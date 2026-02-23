@extends('layouts.app')

@section('title', 'Редактировать товар')

@section('content')
<h1 class="h4 mb-4">Редактировать товар</h1>
<form method="post" action="{{ route('items.update', $item) }}">@csrf @method('PUT')
    <div class="mb-3"><label class="form-label">Название *</label><input type="text" name="name" class="form-control" value="{{ old('name', $item->name) }}" required></div>
    <div class="mb-3"><label class="form-label">Описание</label><textarea name="description" class="form-control" rows="2">{{ old('description', $item->description) }}</textarea></div>
    <div class="mb-3"><label class="form-label">Текущая цена</label><input type="number" name="current_price" class="form-control" step="0.01" min="0" value="{{ old('current_price', $item->current_price) }}"></div>
    <div class="mb-3"><label class="form-label">Статус</label><select name="status_id" class="form-select"><option value="">—</option>@foreach($statuses as $s)<option value="{{ $s->id }}" {{ old('status_id', $item->status_id) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>@endforeach</select></div>
    <div class="mb-3"><label class="form-label">Место хранения</label><select name="storage_location_id" class="form-select"><option value="">—</option>@foreach($locations as $loc)<option value="{{ $loc->id }}" {{ old('storage_location_id', $item->storage_location_id) == $loc->id ? 'selected' : '' }}>{{ $loc->name }}</option>@endforeach</select></div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="{{ route('items.show', $item) }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
