@extends('layouts.app')

@section('title', 'Новое место хранения')

@section('content')
<h1 class="h4 mb-4">Новое место хранения</h1>
<form method="post" action="{{ route('storage-locations.store') }}">@csrf
    <div class="mb-3"><label class="form-label">Магазин *</label><select name="store_id" class="form-select" required>@foreach($stores as $s)<option value="{{ $s->id }}" {{ old('store_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>@endforeach</select></div>
    <div class="mb-3"><label class="form-label">Название *</label><input type="text" name="name" class="form-control" value="{{ old('name') }}" required></div>
    <button type="submit" class="btn btn-primary">Создать</button>
    <a href="{{ route('storage-locations.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
