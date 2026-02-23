@extends('layouts.app')

@section('title', 'Отчёт по кассам')

@section('content')
<h1 class="h4 mb-4">Отчёт по кассам</h1>

<div class="card mb-4">
    <div class="card-body">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Магазин / Касса</th>
                    <th class="text-end">Баланс</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stores as $s)
                <tr>
                    <td>{{ $s->name }}</td>
                    <td class="text-end {{ ($totals[$s->id] ?? 0) >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($totals[$s->id] ?? 0, 0, ',', ' ') }} ₽
                    </td>
                </tr>
                @endforeach
            </tbody>
            <tfoot class="table-light">
                <tr>
                    <th>Итого по всем кассам</th>
                    <th class="text-end {{ $grandTotal >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ number_format($grandTotal, 0, ',', ' ') }} ₽
                    </th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<a href="{{ route('cash.index') }}" class="btn btn-outline-primary">← К списку операций</a>
@endsection
