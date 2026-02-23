@extends('layouts.app')

@section('title', 'Договоры скупки')

@section('content')
<h1 class="h4 mb-4">Договоры скупки</h1>
<table class="table table-hover">
    <thead><tr><th>№ договора</th><th>Клиент</th><th>Товар</th><th>Сумма скупки</th><th>Дата</th><th></th></tr></thead>
    <tbody>
        @foreach($contracts as $c)
        <tr>
            <td>{{ $c->contract_number }}</td>
            <td><a href="{{ route('clients.show', $c->client) }}">{{ $c->client->full_name }}</a></td>
            <td><a href="{{ route('items.show', $c->item) }}">{{ $c->item->name }}</a></td>
            <td>{{ number_format($c->purchase_amount, 0, '', ' ') }} ₽</td>
            <td>{{ \Carbon\Carbon::parse($c->purchase_date)->format('d.m.Y') }}</td>
            <td><a href="{{ route('purchase-contracts.show', $c) }}" class="btn btn-sm btn-outline-primary">Подробнее</a></td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $contracts->links() }}
@endsection
