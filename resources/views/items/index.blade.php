@extends('layouts.app')

@section('title', 'Товары')

@section('content')
<h1 class="h4 mb-4">Товары</h1>
<form method="get" class="mb-3 row g-2">
    <div class="col-auto"><input type="text" name="search" class="form-control form-control-sm" placeholder="Название, штрихкод" value="{{ request('search') }}"></div>
    <div class="col-auto"><select name="status_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Все статусы</option>@foreach($statuses as $s)<option value="{{ $s->id }}" {{ request('status_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>@endforeach</select></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-secondary">Найти</button></div>
</form>
<table class="table table-hover">
    <thead><tr><th>Штрихкод</th><th>Название</th><th>Магазин</th><th>Статус</th><th>Цена</th><th></th></tr></thead>
    <tbody>
        @foreach($items as $i)
        <tr>
            <td><code>{{ $i->barcode }}</code></td>
            <td><a href="{{ route('items.show', $i) }}">{{ $i->name }}</a></td>
            <td>{{ $i->store->name }}</td>
            <td>@if($i->status)<span class="badge" @if($i->status->color) style="background-color:{{ $i->status->color }}" @endif>{{ $i->status->name }}</span>@else—@endif</td>
            <td>{{ $i->current_price ? number_format($i->current_price, 0, '', ' ') . ' ₽' : '—' }}</td>
            <td>
                <a href="{{ route('items.show', $i) }}" class="btn btn-sm btn-outline-primary">Карточка</a>
                @if(auth()->user()->canManageStorage())
                <a href="{{ route('items.edit', $i) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
{{ $items->links() }}
@endsection
