@extends('layouts.app')

@section('title', 'Изменить вид расхода')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Изменить вид расхода</h1>
    <a href="{{ route('expense-types.index') }}" class="btn btn-outline-secondary">К списку</a>
</div>
<form method="post" action="{{ route('expense-types.update', $expenseType) }}">
    @csrf @method('PUT')
    <div class="card mb-3">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Название *</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $expenseType->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Код</label>
                <input type="text" name="code" class="form-control" value="{{ old('code', $expenseType->code) }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Счёт учёта</label>
                <select name="account_id" class="form-select">
                    <option value="">— не указан</option>
                    @foreach($accounts as $a)
                    <option value="{{ $a->id }}" @selected(old('account_id', $expenseType->account_id) == $a->id)>{{ $a->code }} {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $expenseType->is_active))>
                    <label class="form-check-label" for="is_active">Активен</label>
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label">Порядок сортировки</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $expenseType->sort_order) }}" min="0">
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
</form>
@endsection
