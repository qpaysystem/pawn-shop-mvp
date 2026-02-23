@extends('layouts.admin')
@section('title', 'Новый товар')
@section('content')
<h1 class="h4 mb-4">Новый товар (ТМЦ)</h1>
<form method="post" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="row g-3">
        <div class="col-md-12">
            <label class="form-label">Название *</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Вид</label>
            <input type="text" name="kind" class="form-control @error('kind') is-invalid @enderror" value="{{ old('kind') }}" placeholder="например: Оборудование">
            @error('kind')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Тип</label>
            <input type="text" name="type" class="form-control @error('type') is-invalid @enderror" value="{{ old('type') }}" placeholder="например: Компьютер">
            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Оценочная стоимость</label>
            <input type="number" name="estimated_cost" step="0.01" min="0" class="form-control @error('estimated_cost') is-invalid @enderror" value="{{ old('estimated_cost') }}">
            @error('estimated_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Фото</label>
            <input type="file" name="photo" class="form-control @error('photo') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
            @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label">Описание</label>
            <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description') }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary">Создать</button>
        <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">Отмена</a>
    </div>
</form>
@endsection
