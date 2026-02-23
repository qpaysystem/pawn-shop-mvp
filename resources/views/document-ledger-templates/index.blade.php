@extends('layouts.app')

@section('title', 'Шаблоны проводок по документам')

@section('content')
<h1 class="h4 mb-4"><i class="bi bi-journal-check me-2"></i>Шаблоны проводок (отражение в ОСВ)</h1>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="mb-3">
    <a href="{{ route('document-ledger-templates.create') }}" class="btn btn-primary">Добавить шаблон</a>
    <a href="{{ route('chart-of-accounts.index') }}" class="btn btn-outline-secondary">План счетов</a>
</div>

<form method="get" class="mb-3">
    <label class="form-label">Тип документа</label>
    <select name="document_type" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
        <option value="">Все</option>
        @foreach($typeLabels as $code => $label)
            <option value="{{ $code }}" {{ $typeFilter === $code ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</form>

@foreach($grouped as $docType => $items)
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>{{ $typeLabels[$docType] ?? $docType }}</span>
        <a href="{{ route('document-ledger-templates.create', ['document_type' => $docType]) }}" class="btn btn-sm btn-outline-primary">Добавить шаблон</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Название</th>
                    <th>Дебет (счёт)</th>
                    <th>Кредит (счёт)</th>
                    <th>Поле суммы</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $t)
                <tr>
                    <td>{{ $t->name ?? '—' }}</td>
                    <td><code>{{ $t->debit_account_code }}</code> {{ \App\Models\Account::findByCode($t->debit_account_code)?->name }}</td>
                    <td><code>{{ $t->credit_account_code }}</code> {{ \App\Models\Account::findByCode($t->credit_account_code)?->name }}</td>
                    <td><code>{{ $t->amount_field ?? '—' }}</code></td>
                    <td>
                        <form action="{{ route('document-ledger-templates.destroy', $t) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить шаблон?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endforeach

@if($grouped->isEmpty())
    <p class="text-muted">Шаблонов пока нет. Добавьте шаблоны проводок для каждого типа документа — по ним можно будет формировать проводки при создании документов.</p>
@endif
@endsection
