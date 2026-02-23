@extends('layouts.app')

@section('title', 'Выписка ' . \Carbon\Carbon::parse($statement->date_from)->format('d.m.Y'))

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Выписка {{ \Carbon\Carbon::parse($statement->date_from)->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($statement->date_to)->format('d.m.Y') }}</h1>
    <div>
        <a href="{{ route('bank-accounts.statements.index', $bankAccount) }}" class="btn btn-outline-secondary me-2">К выпискам</a>
        @if($statement->file_path)
        <a href="{{ route('bank-accounts.statements.download', [$bankAccount, $statement]) }}" class="btn btn-outline-primary">Скачать файл</a>
        @endif
    </div>
</div>
<div class="card mb-3">
    <div class="card-body">
        <p><strong>Счёт:</strong> {{ $bankAccount->name }}</p>
        <p><strong>Начальное сальдо:</strong> {{ $statement->opening_balance !== null ? number_format($statement->opening_balance, 2, ',', ' ') . ' ₽' : '—' }}</p>
        <p><strong>Конечное сальдо:</strong> {{ $statement->closing_balance !== null ? number_format($statement->closing_balance, 2, ',', ' ') . ' ₽' : '—' }}</p>
        @if($statement->notes)<p><strong>Примечание:</strong> {{ $statement->notes }}</p>@endif
    </div>
</div>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Движения</span>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addLineModal"><i class="bi bi-plus"></i> Строка</button>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Дата</th><th>Сумма</th><th>Контрагент</th><th>Назначение</th><th>№ док.</th></tr>
            </thead>
            <tbody>
                @forelse($statement->lines as $line)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($line->line_date)->format('d.m.Y') }}</td>
                    <td class="{{ $line->amount >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($line->amount, 2, ',', ' ') }} ₽</td>
                    <td>{{ $line->counterparty ?? '—' }}</td>
                    <td>{{ $line->description ?? '—' }}</td>
                    <td>{{ $line->document_number ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-muted">Нет движений. Добавьте строки вручную или загрузите файл при создании выписки.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addLineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('bank-accounts.statements.lines.store', [$bankAccount, $statement]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Добавить строку</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Дата *</label>
                        <input type="date" name="line_date" class="form-control" value="{{ \Carbon\Carbon::parse($statement->date_from)->format('Y-m-d') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Сумма (₽) * <small class="text-muted">положительная — приход, отрицательная — расход</small></label>
                        <input type="number" name="amount" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Контрагент</label>
                        <input type="text" name="counterparty" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Назначение</label>
                        <input type="text" name="description" class="form-control">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">№ документа</label>
                        <input type="text" name="document_number" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<a href="{{ route('bank-accounts.index') }}" class="btn btn-secondary">К расчётным счетам</a>
@endsection
