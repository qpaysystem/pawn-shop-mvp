@extends('layouts.app')

@section('title', 'Начислить расход')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Документ начисления расхода</h1>
    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">К списку</a>
</div>
<form method="post" action="{{ route('expenses.store') }}">
    @csrf
    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Вид расхода *</label>
                    <select name="expense_type_id" class="form-select @error('expense_type_id') is-invalid @enderror" required>
                        <option value="">— выберите</option>
                        @foreach($expenseTypes as $et)
                        <option value="{{ $et->id }}" @selected(old('expense_type_id') == $et->id)>{{ $et->name }}</option>
                        @endforeach
                    </select>
                    @error('expense_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Сумма (₽) *</label>
                    <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" step="0.01" min="0.01" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Дата расхода *</label>
                    <input type="date" name="expense_date" class="form-control @error('expense_date') is-invalid @enderror" value="{{ old('expense_date', date('Y-m-d')) }}" required>
                    @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label">Комментарий</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Создать документ</button>
</form>
@endsection
