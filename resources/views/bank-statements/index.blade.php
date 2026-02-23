@extends('layouts.app')

@section('title', 'Выписки — ' . $bankAccount->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-file-earmark-text me-2"></i>Выписки: {{ $bankAccount->name }}</h1>
    <div>
        <a href="{{ route('bank-accounts.index') }}" class="btn btn-outline-secondary me-2">К счетам</a>
        <a href="{{ route('bank-accounts.statements.create', $bankAccount) }}" class="btn btn-primary"><i class="bi bi-plus"></i> Добавить выписку</a>
    </div>
</div>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr><th>Период</th><th>Начальное сальдо</th><th>Конечное сальдо</th><th>Файл</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($statements as $s)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($s->date_from)->format('d.m.Y') }} — {{ \Carbon\Carbon::parse($s->date_to)->format('d.m.Y') }}</td>
                    <td>{{ $s->opening_balance !== null ? number_format($s->opening_balance, 2, ',', ' ') . ' ₽' : '—' }}</td>
                    <td>{{ $s->closing_balance !== null ? number_format($s->closing_balance, 2, ',', ' ') . ' ₽' : '—' }}</td>
                    <td>@if($s->file_name)<a href="{{ route('bank-accounts.statements.download', [$bankAccount, $s]) }}">{{ $s->file_name }}</a>@else — @endif</td>
                    <td><a href="{{ route('bank-accounts.statements.show', [$bankAccount, $s]) }}" class="btn btn-sm btn-outline-primary">Подробнее</a></td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-muted">Нет выписок. <a href="{{ route('bank-accounts.statements.create', $bankAccount) }}">Добавить выписку</a></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $statements->links() }}
@endsection
