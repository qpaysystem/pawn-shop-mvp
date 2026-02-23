@extends('layouts.app')

@section('title', 'Новая выписка')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Добавить выписку: {{ $bankAccount->name }}</h1>
    <a href="{{ route('bank-accounts.statements.index', $bankAccount) }}" class="btn btn-outline-secondary">К выпискам</a>
</div>
<form method="post" action="{{ route('bank-accounts.statements.store', $bankAccount) }}" enctype="multipart/form-data">
    @csrf
    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Дата с *</label>
                    <input type="date" name="date_from" class="form-control @error('date_from') is-invalid @enderror" value="{{ old('date_from') }}" required>
                    @error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Дата по *</label>
                    <input type="date" name="date_to" class="form-control @error('date_to') is-invalid @enderror" value="{{ old('date_to') }}" required>
                    @error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Начальное сальдо (₽)</label>
                    <input type="number" name="opening_balance" class="form-control" step="0.01" value="{{ old('opening_balance') }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Конечное сальдо (₽)</label>
                    <input type="number" name="closing_balance" class="form-control" step="0.01" value="{{ old('closing_balance') }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Файл выписки (PDF, CSV, Excel)</label>
                <input type="file" name="file" class="form-control" accept=".pdf,.csv,.txt,.xlsx,.xls">
            </div>
            <div class="mb-0">
                <label class="form-label">Примечание</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Создать</button>
</form>
@endsection
