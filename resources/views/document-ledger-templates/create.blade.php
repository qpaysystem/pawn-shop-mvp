@extends('layouts.app')

@section('title', 'Добавить шаблон проводки')

@section('content')
<h1 class="h4 mb-4">Добавить шаблон проводки</h1>

<form method="post" action="{{ route('document-ledger-templates.store') }}">
    @csrf
    <div class="card mb-3">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Тип документа</label>
                <select name="document_type" class="form-select" required>
                    @foreach($typeLabels as $code => $label)
                        <option value="{{ $code }}" {{ $documentType === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Название шаблона (необязательно)</label>
                <input type="text" name="name" class="form-control" placeholder="Например: Выдача займа">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Дебет (счёт)</label>
                    <select name="debit_account_code" class="form-select" required>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->code }}">{{ $acc->code }} — {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Кредит (счёт)</label>
                    <select name="credit_account_code" class="form-select" required>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->code }}">{{ $acc->code }} — {{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Поле документа с суммой</label>
                <input type="text" name="amount_field" class="form-control" placeholder="Например: loan_amount, amount, total_amount">
                <small class="text-muted">Для договора залога: loan_amount. Для кассы: amount. Для ФОТ: total_amount.</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Шаблон комментария (необязательно)</label>
                <input type="text" name="comment_template" class="form-control" placeholder="Например: Договор залога №{contract_number}">
            </div>
            <div class="mb-3">
                <label class="form-label">Порядок</label>
                <input type="number" name="sort_order" class="form-control" value="0" min="0">
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="{{ route('document-ledger-templates.index') }}" class="btn btn-outline-secondary">Отмена</a>
</form>
@endsection
