@extends('layouts.app')

@section('title', 'Профиль')

@section('content')
<h1 class="h4 mb-4">Профиль: {{ $user->name }}</h1>
<div class="card mb-4">
    <div class="card-body">
        <p class="mb-1"><strong>Роль:</strong> {{ $user->role }}</p>
        @if($user->store)<p class="mb-1"><strong>Магазин:</strong> {{ $user->store->name }}</p>@endif
        <p class="mb-0"><strong>Email:</strong> {{ $user->email }}</p>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="profileTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pawn-tab" data-bs-toggle="tab" data-bs-target="#pawn" type="button" role="tab">Договоры займа</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="commission-tab" data-bs-toggle="tab" data-bs-target="#commission" type="button" role="tab">Договоры комиссии</button>
    </li>
</ul>
<div class="tab-content" id="profileTabsContent">
    <div class="tab-pane fade show active" id="pawn" role="tabpanel">
        <p class="text-muted small">Договоры залога, где вы указаны приёмщиком (оценщиком).</p>
        @if($pawnContracts->isEmpty())
            <p class="text-muted">Нет договоров.</p>
        @else
            <table class="table table-hover">
                <thead><tr><th>№</th><th>Клиент</th><th>Товар</th><th>Сумма</th><th>Выкуп</th><th></th></tr></thead>
                <tbody>
                    @foreach($pawnContracts as $c)
                    <tr>
                        <td>{{ $c->contract_number }}</td>
                        <td>{{ $c->client->full_name }}</td>
                        <td>{{ $c->item->name }}</td>
                        <td>{{ number_format($c->loan_amount, 0, '', ' ') }} ₽</td>
                        <td>@if($c->is_redeemed)<span class="badge bg-success">Да</span>@else<span class="badge bg-warning">Нет</span>@endif</td>
                        <td><a href="{{ route('pawn-contracts.show', $c) }}" class="btn btn-sm btn-outline-primary">Открыть</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $pawnContracts->withQueryString()->links() }}
        @endif
    </div>
    <div class="tab-pane fade" id="commission" role="tabpanel">
        <p class="text-muted small">Договоры комиссии, где вы указаны приёмщиком (оценщиком).</p>
        @if($commissionContracts->isEmpty())
            <p class="text-muted">Нет договоров.</p>
        @else
            <table class="table table-hover">
                <thead><tr><th>№</th><th>Клиент</th><th>Товар</th><th>Цена</th><th>Продано</th><th></th></tr></thead>
                <tbody>
                    @foreach($commissionContracts as $c)
                    <tr>
                        <td>{{ $c->contract_number }}</td>
                        <td>{{ $c->client->full_name }}</td>
                        <td>{{ $c->item->name }}</td>
                        <td>{{ $c->seller_price ? number_format($c->seller_price, 0, '', ' ') . ' ₽' : '—' }}</td>
                        <td>@if($c->is_sold)<span class="badge bg-success">Да</span>@else<span class="badge bg-warning">Нет</span>@endif</td>
                        <td><a href="{{ route('commission-contracts.show', $c) }}" class="btn btn-sm btn-outline-primary">Открыть</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $commissionContracts->withQueryString()->links() }}
        @endif
    </div>
</div>
@endsection
