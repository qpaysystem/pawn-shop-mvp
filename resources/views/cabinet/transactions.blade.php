@extends('cabinet.layout')
@section('title', 'Транзакции')
@section('content')
<h1 class="h4 mb-4">История операций</h1>
@php $client->load(['balanceTransactions.product', 'balanceTransactions.project', 'balanceTransactions.projectExpenseItem']); @endphp
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Дата</th><th>Тип</th><th>Залог / Проект</th><th>Сумма</th><th>Баланс после</th><th>Комментарий</th></tr></thead>
                <tbody>
                    @forelse($client->balanceTransactions as $t)
                    <tr>
                        <td>{{ $t->created_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $t->operation_type_label }}</td>
                        <td>@if($t->operation_type === \App\Models\BalanceTransaction::OPERATION_PROJECT_EXPENSE){{ $t->project?->name }}@if($t->projectExpenseItem) — {{ $t->projectExpenseItem->name }}@endif @else{{ $t->product?->name ?? '—' }}@endif</td>
                        <td class="{{ $t->type === 'deposit' ? 'text-success' : 'text-danger' }}">
                            {{ $t->type === 'deposit' ? '+' : '−' }}{{ number_format($t->amount, 2) }}
                        </td>
                        <td>{{ number_format($t->balance_after, 2) }}</td>
                        <td class="small text-muted">{{ Str::limit($t->comment, 40) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-muted text-center py-4">Нет операций</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
