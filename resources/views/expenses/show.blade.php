@extends('layouts.app')

@section('title', 'Расход ' . $expense->number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Расход {{ $expense->number }}</h1>
    <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">К списку</a>
</div>
<div class="card">
    <div class="card-body">
        <p><strong>Дата:</strong> {{ \Carbon\Carbon::parse($expense->expense_date)->format('d.m.Y') }}</p>
        <p><strong>Вид расхода:</strong> {{ $expense->expenseType->name }}</p>
        <p><strong>Магазин:</strong> {{ $expense->store?->name ?? '—' }}</p>
        <p><strong>Сумма:</strong> {{ number_format($expense->amount, 2, ',', ' ') }} ₽</p>
        @if($expense->description)<p><strong>Комментарий:</strong> {{ $expense->description }}</p>@endif
        @if($expense->createdByUser)<p class="text-muted small mb-0">Создал: {{ $expense->createdByUser->name ?? $expense->createdByUser->email }}, {{ \Carbon\Carbon::parse($expense->created_at)->format('d.m.Y H:i') }}</p>@endif
    </div>
</div>
@endsection
