@extends('layouts.admin')
@section('title', 'Новый проект')
@section('content')
<div class="mb-3">
    <a href="{{ route('admin.projects.index') }}" class="btn btn-sm btn-outline-secondary">← К списку проектов</a>
</div>
<h1 class="h4 mb-4">Новый проект</h1>
<form method="post" action="{{ route('admin.projects.store') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label">Название *</label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label">Описание</label>
        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-primary">Создать проект</button>
    <a href="{{ route('admin.projects.index') }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
