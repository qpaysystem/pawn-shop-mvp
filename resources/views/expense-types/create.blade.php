@extends('layouts.app')

@section('title', 'Добавить вид расхода')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Новый вид расхода</h1>
    <a href="{{ route('expense-types.index') }}" class="btn btn-outline-secondary">К списку</a>
</div>
<form method="post" action="{{ route('expense-types.store') }}">
    @csrf
    <div class="card mb-3">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Название *</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Код</label>
                <input type="text" name="code" class="form-control" value="{{ old('code') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Счёт учёта (план счетов)</label>
                <select name="account_id" class="form-select">
                    <option value="">— не указан</option>
                    @foreach($accounts as $a)
                    <option value="{{ $a->id }}" @selected(old('account_id') == $a->id)>{{ $a->code }} {{ $a->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-0">
                <label class="form-label">Порядок сортировки</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Создать</button>
</form>
@endsection
