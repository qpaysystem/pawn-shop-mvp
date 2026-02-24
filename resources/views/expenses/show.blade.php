@extends('layouts.app')

@section('title', 'Расход ' . $expense->number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Расход {{ $expense->number }}</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">К списку</a>
        <form action="{{ route('expenses.destroy', $expense) }}" method="post" class="d-inline" onsubmit="return confirm('Удалить документ начисления расхода? Проводки в ОСВ по этому документу также будут удалены.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Удалить</button>
        </form>
    </div>
</div>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-document">Документ</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-ledger">Бухгалтерские проводки</a></li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="tab-document">
<div class="card">
    <div class="card-body">
        <p><strong>Дата:</strong> {{ \Carbon\Carbon::parse($expense->expense_date)->format('d.m.Y') }}</p>
        <p><strong>Вид расхода:</strong> {{ $expense->expenseType->name }}</p>
        <p><strong>Магазин:</strong> {{ $expense->store?->name ?? '—' }}</p>
        <p><strong>Клиент (долг):</strong> @if($expense->client)<a href="{{ route('clients.show', $expense->client) }}">{{ $expense->client->full_name }}</a>@else —@endif</p>
        <p><strong>Сумма:</strong> {{ number_format($expense->amount, 2, ',', ' ') }} ₽</p>
        @if($expense->description)<p><strong>Комментарий:</strong> {{ $expense->description }}</p>@endif
        @if($expense->createdByUser)<p class="text-muted small mb-0">Создал: {{ $expense->createdByUser->name ?? $expense->createdByUser->email }}, {{ \Carbon\Carbon::parse($expense->created_at)->format('d.m.Y H:i') }}</p>@endif
    </div>
</div>
</div>
<div class="tab-pane fade" id="tab-ledger">
    @include('documents._ledger_tab', [
        'documentType' => $documentType,
        'documentId' => $documentId,
        'ledgerEntries' => $ledgerEntries,
        'templates' => $templates,
    ])
</div>
</div>
@endsection
