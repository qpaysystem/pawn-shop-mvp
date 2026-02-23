@extends('layouts.app')

@section('title', 'Договоры комиссии')

@section('content')
<h1 class="h4 mb-4">Договоры комиссии</h1>
<form method="get" class="mb-3">
    <select name="sold" class="form-select form-select-sm w-auto d-inline-block" onchange="this.form.submit()">
        <option value="">Все</option>
        <option value="0" {{ request('sold') === '0' ? 'selected' : '' }}>Не проданы</option>
        <option value="1" {{ request('sold') === '1' ? 'selected' : '' }}>Проданные</option>
    </select>
</form>
<table class="table table-hover">
    <thead><tr><th>№ договора</th><th>Клиент</th><th>Товар</th><th>Цена продажи</th><th>Продано</th><th></th></tr></thead>
    <tbody>
        @foreach($contracts as $c)
        <tr>
            <td>{{ $c->contract_number }}</td>
            <td><a href="{{ route('clients.show', $c->client) }}">{{ $c->client->full_name }}</a></td>
            <td><a href="{{ route('items.show', $c->item) }}">{{ $c->item->name }}</a></td>
            <td>{{ $c->seller_price ? number_format($c->seller_price, 0, '', ' ') . ' ₽' : '—' }}</td>
            <td>@if($c->is_sold)<span class="badge bg-success">Да</span>@else<span class="badge bg-warning">Нет</span>@endif</td>
            <td><a href="{{ route('commission-contracts.show', $c) }}" class="btn btn-sm btn-outline-primary">Подробнее</a></td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $contracts->links() }}
@endsection
