@extends('layouts.app')

@section('title', 'Добавить сотрудника')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Новый сотрудник</h1>
    <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary">К списку</a>
</div>
<form method="post" action="{{ route('employees.store') }}">
    @csrf
    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Фамилия *</label>
                    <input type="text" name="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name') }}" required>
                    @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Имя *</label>
                    <input type="text" name="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name') }}" required>
                    @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Отчество</label>
                    <input type="text" name="patronymic" class="form-control" value="{{ old('patronymic') }}">
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Должность</label>
                    <input type="text" name="position" class="form-control" value="{{ old('position') }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Магазин</label>
                    <select name="store_id" class="form-select">
                        <option value="">— не указан</option>
                        @foreach($stores as $s)
                        <option value="{{ $s->id }}" @selected(old('store_id') == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-check mb-0">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', true))>
                <label class="form-check-label" for="is_active">Активен (участвует в начислениях)</label>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Добавить</button>
</form>
@endsection
