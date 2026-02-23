@extends('layouts.app')

@section('title', 'Редактировать бренд')

@section('content')
<h1 class="h4 mb-4">Редактировать бренд</h1>
<form method="post" action="{{ route('brands.update', $brand) }}">@csrf @method('PUT')
    <div class="mb-3"><label class="form-label">Название *</label><input type="text" name="name" class="form-control" value="{{ old('name', $brand->name) }}" required></div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="{{ route('brands.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
