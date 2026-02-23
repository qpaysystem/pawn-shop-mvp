@extends('layouts.app')

@section('title', 'Изменить расчётный счёт')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Изменить расчётный счёт</h1>
    <a href="{{ route('bank-accounts.index') }}" class="btn btn-outline-secondary">К списку</a>
</div>
<form method="post" action="{{ route('bank-accounts.update', $bankAccount) }}">
    @csrf @method('PUT')
    <div class="card mb-3">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Название *</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $bankAccount->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Банк</label>
                <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $bankAccount->bank_name) }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Номер счёта</label>
                <input type="text" name="account_number" class="form-control" value="{{ old('account_number', $bankAccount->account_number) }}">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">БИК</label>
                    <input type="text" name="bik" class="form-control" value="{{ old('bik', $bankAccount->bik) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Корр. счёт</label>
                    <input type="text" name="correspondent_account" class="form-control" value="{{ old('correspondent_account', $bankAccount->correspondent_account) }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Магазин</label>
                <select name="store_id" class="form-select">
                    <option value="">— не указан</option>
                    @foreach($stores as $s)
                    <option value="{{ $s->id }}" @selected(old('store_id', $bankAccount->store_id) == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-check mb-0">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $bankAccount->is_active))>
                <label class="form-check-label" for="is_active">Активен</label>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
</form>
@endsection
