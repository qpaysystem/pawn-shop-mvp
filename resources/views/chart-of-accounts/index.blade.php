@extends('layouts.app')

@section('title', 'План бухгалтерских счетов')

@section('content')
<h1 class="h4 mb-4"><i class="bi bi-journal-ruled me-2"></i>План бухгалтерских счетов</h1>

<div class="mb-3">
    <a href="{{ route('chart-of-accounts.turnover-balance') }}" class="btn btn-primary"><i class="bi bi-table"></i> Оборотно-сальдовая ведомость</a>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Номер счёта</th>
                    <th>Название счёта</th>
                    <th>Назначение / Аналитика</th>
                    <th>Тип</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($accounts as $account)
                <tr>
                    <td><strong>{{ $account->code }}</strong></td>
                    <td>{{ $account->name }}</td>
                    <td class="text-muted small">{{ $account->description ?? '—' }}</td>
                    <td>
                        @if($account->type === 'active')
                            <span class="badge bg-primary">Активный</span>
                        @elseif($account->type === 'passive')
                            <span class="badge bg-secondary">Пассивный</span>
                        @else
                            <span class="badge bg-info">Активно-пассивный</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('chart-of-accounts.show', $account) }}" class="btn btn-sm btn-outline-primary">Карточка счёта</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@if($accounts->isEmpty())
    <p class="text-muted mt-3">Счета не найдены. Выполните: <code>php artisan db:seed --class=AccountsSeeder</code></p>
@endif
@endsection
