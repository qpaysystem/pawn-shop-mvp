@extends('layouts.app')

@section('title', 'Договоры залога')

@section('content')
<h1 class="h4 mb-4">Договоры залога</h1>
<form method="get" class="mb-3">
    <select name="redeemed" class="form-select form-select-sm w-auto d-inline-block" onchange="this.form.submit()">
        <option value="">Все</option>
        <option value="0" {{ request('redeemed') === '0' ? 'selected' : '' }}>Активные</option>
        <option value="1" {{ request('redeemed') === '1' ? 'selected' : '' }}>Выкупленные</option>
    </select>
</form>
<table class="table table-hover">
    <thead><tr><th>№ договора</th><th>Клиент</th><th>Товар</th><th>Сумма займа</th><th>Выкуп</th><th>Срок</th><th></th></tr></thead>
    <tbody>
        @foreach($contracts as $c)
        <tr>
            <td>{{ $c->contract_number }}</td>
            <td><a href="{{ route('clients.show', $c->client) }}">{{ $c->client->full_name }}</a></td>
            <td><a href="{{ route('items.show', $c->item) }}">{{ $c->item->name }}</a></td>
            <td>{{ number_format($c->loan_amount, 0, '', ' ') }} ₽</td>
            <td>{{ number_format($c->redemption_amount, 0, '', ' ') }} ₽</td>
            <td>@if($c->is_redeemed)<span class="badge bg-success">Да</span>@else<span class="badge bg-warning">Нет</span>@endif</td>
            <td>{{ \Carbon\Carbon::parse($c->expiry_date)->format('d.m.Y') }}</td>
            <td><a href="{{ route('pawn-contracts.show', $c) }}" class="btn btn-sm btn-outline-primary">Подробнее</a></td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $contracts->links() }}
@endsection
