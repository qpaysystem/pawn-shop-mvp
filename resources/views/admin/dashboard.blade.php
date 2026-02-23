@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
<h1 class="h4 mb-4">Dashboard</h1>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted">Всего клиентов</h6>
                <h3 class="mb-0">{{ $stats['clients_total'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted">Активных</h6>
                <h3 class="mb-0">{{ $stats['clients_active'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted">Общий баланс</h6>
                <h3 class="mb-0">{{ number_format($stats['balance_total'], 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6 class="text-muted">Баланс товаров (ТМЦ)</h6>
                <h3 class="mb-0">{{ number_format($stats['products_balance'], 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</h3>
                <small class="text-muted">{{ $stats['products_count'] }} позиций в ТМЦ</small>
            </div>
        </div>
    </div>
</div>
<h5 class="mb-3">Последние клиенты</h5>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Имя</th><th>Email</th><th>Баланс</th><th></th></tr></thead>
            <tbody>
                @forelse($recentClients as $c)
                <tr>
                    <td>{{ $c->full_name }}</td>
                    <td>{{ $c->email }}</td>
                    <td>{{ number_format($c->balance, 2) }}</td>
                    <td><a href="{{ route('admin.clients.show', $c) }}" class="btn btn-sm btn-outline-primary">Открыть</a></td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-muted">Нет клиентов</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
