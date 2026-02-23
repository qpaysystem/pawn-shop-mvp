@extends('layouts.admin')
@section('title', 'Редактировать товар')
@section('content')
<h1 class="h4 mb-4">Редактировать товар</h1>
@if($product->isPledge())
    <div class="alert alert-warning py-2 mb-3"><i class="bi bi-key"></i> Статус: <strong>Залог</strong> (товар связан с займом)</div>
@endif
<div class="row">
    <div class="col-md-4">
        @if($product->photo_path)
            <img src="{{ asset('storage/'.$product->photo_path) }}" alt="" class="img-fluid rounded mb-3" style="max-height: 300px; object-fit: cover;">
            <form method="post" action="{{ route('admin.products.photo.delete', $product) }}" class="d-inline" onsubmit="return confirm('Удалить фото?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить фото</button>
            </form>
        @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3" style="height: 200px;"><span class="text-muted">Нет фото</span></div>
        @endif
        <form method="post" action="{{ route('admin.products.photo', $product) }}" enctype="multipart/form-data" class="mt-2">
            @csrf
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm">
            <button type="submit" class="btn btn-sm btn-primary mt-1">Загрузить фото</button>
        </form>
    </div>
    <div class="col-md-8">
        <form method="post" action="{{ route('admin.products.update', $product) }}">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="form-label">Название *</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $product->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Вид</label>
                    <input type="text" name="kind" class="form-control" value="{{ old('kind', $product->kind) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Тип</label>
                    <input type="text" name="type" class="form-control" value="{{ old('type', $product->type) }}">
                </div>
            </div>
            <div class="mb-3 mt-3">
                <label class="form-label">Оценочная стоимость</label>
                <input type="number" name="estimated_cost" step="0.01" min="0" class="form-control" value="{{ old('estimated_cost', $product->estimated_cost) }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Описание</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $product->description) }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>
            <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">Отмена</a>
        </form>
    </div>
</div>
@endsection
