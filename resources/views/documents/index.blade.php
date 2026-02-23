@extends('layouts.app')

@section('title', 'Все документы')

@section('content')
<h1 class="h4 mb-4"><i class="bi bi-files me-2"></i>Все документы</h1>

<form method="get" class="row g-3 mb-4">
    <div class="col-auto">
        <label class="form-label">Дата с</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}" onchange="this.form.submit()">
    </div>
    <div class="col-auto">
        <label class="form-label">Дата по</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}" onchange="this.form.submit()">
    </div>
    <div class="col-auto">
        <label class="form-label">Тип</label>
        <select name="type" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
            <option value="">Все</option>
            <option value="pawn_contract" {{ $typeFilter === 'pawn_contract' ? 'selected' : '' }}>Договоры залога</option>
            <option value="commission_contract" {{ $typeFilter === 'commission_contract' ? 'selected' : '' }}>Договоры комиссии</option>
            <option value="purchase_contract" {{ $typeFilter === 'purchase_contract' ? 'selected' : '' }}>Договоры скупки</option>
            <option value="cash_document" {{ $typeFilter === 'cash_document' ? 'selected' : '' }}>Кассовые документы</option>
            <option value="payroll_accrual" {{ $typeFilter === 'payroll_accrual' ? 'selected' : '' }}>Начисления ФОТ</option>
            <option value="expense" {{ $typeFilter === 'expense' ? 'selected' : '' }}>Расходы</option>
        </select>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Тип документа</th>
                        <th>Номер</th>
                        <th>Дата</th>
                        <th class="text-end">Сумма</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->type_label }}</td>
                        <td>{{ $row->number }}</td>
                        <td>{{ $row->date ? \Carbon\Carbon::parse($row->date)->format('d.m.Y') : '—' }}</td>
                        <td class="text-end">{{ isset($row->amount) ? number_format($row->amount, 0, ',', ' ') . ' ₽' : '—' }}</td>
                        <td>
                            <a href="{{ $row->url }}" class="btn btn-sm btn-outline-primary">Открыть</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-muted text-center py-4">Нет документов за выбранный период.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($paginator->hasPages())
    <div class="card-footer">
        {{ $paginator->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
