@extends('layouts.admin')
@section('title', 'Изменить проект')
@section('content')
<h1 class="h4 mb-4">Изменить проект</h1>
<form method="post" action="{{ route('admin.projects.update', $project) }}">
    @csrf
    @method('PUT')
    <div class="mb-3">
        <label class="form-label">Название *</label>
        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $project->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label">Описание</label>
        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $project->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="{{ route('admin.projects.show', $project) }}" class="btn btn-secondary">К карточке</a>
</form>
@endsection
