@extends('layouts.frontend')
@section('title', $client->full_name)
@section('content')
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('frontend.clients.list') }}">Клиенты</a></li>
        <li class="breadcrumb-item active">{{ $client->full_name }}</li>
    </ol>
</nav>
<div class="row">
    <div class="col-md-4 mb-4">
        @if($client->photo_path)
            <img src="{{ asset('storage/'.$client->photo_path) }}" alt="{{ $client->full_name }}" class="img-fluid rounded shadow">
        @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center py-5">
                <i class="bi bi-person display-1 text-muted"></i>
            </div>
        @endif
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <h1 class="h4 card-title">{{ $client->full_name }}</h1>
                <span class="badge bg-{{ $client->status === 'active' ? 'success' : 'secondary' }} mb-3">{{ $client->status === 'active' ? 'Активный' : 'Неактивный' }}</span>
                <table class="table table-borderless mb-0">
                    <tr><th style="width:180px">Email</th><td>{{ $client->email }}</td></tr>
                    <tr><th>Телефон</th><td>{{ $client->phone }}</td></tr>
                    <tr><th>Дата рождения</th><td>{{ $client->birth_date?->format('d.m.Y') }}</td></tr>
                    <tr><th>Дата регистрации</th><td>{{ $client->registered_at?->format('d.m.Y') }}</td></tr>
                    <tr><th>Баланс</th><td><strong>{{ number_format($client->balance, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</strong></td></tr>
                </table>
                @foreach($client->customValues as $cv)
                    @if($cv->customField && ($cv->value !== null && $cv->value !== ''))
                    <p class="mb-1"><strong>{{ $cv->customField->label }}:</strong> {{ $cv->value }}</p>
                    @endif
                @endforeach
            </div>
        </div>
        <div class="card mt-4">
            <div class="card-header">История операций с балансом</div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Дата</th><th>Тип</th><th>Сумма</th><th>Баланс после</th></tr></thead>
                    <tbody>
                        @forelse($client->balanceTransactions as $t)
                        <tr>
                            <td>{{ $t->created_at->format('d.m.Y H:i') }}</td>
                            <td>{{ $t->type === 'deposit' ? 'Пополнение' : 'Списание' }}</td>
                            <td>{{ $t->type === 'deposit' ? '+' : '-' }}{{ number_format($t->amount, 2) }}</td>
                            <td>{{ number_format($t->balance_after, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-muted">Нет операций</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<a href="{{ route('frontend.clients.list') }}" class="btn btn-secondary mt-3">← К списку клиентов</a>
@endsection
