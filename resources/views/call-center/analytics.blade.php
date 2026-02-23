@extends('layouts.app')

@section('title', 'Аналитика колл-центра')

@section('content')
<h1 class="h4 mb-4">Аналитика колл-центра</h1>

<form method="get" class="row g-2 mb-4">
    <div class="col-auto">
        <label class="form-label">Период</label>
        <div class="d-flex gap-2 align-items-end">
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}" style="width:auto">
            <span>—</span>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}" style="width:auto">
            <button type="submit" class="btn btn-sm btn-primary">Применить</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Всего обращений</h6>
                <p class="display-6 mb-0">{{ $totalContacts }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Звонки принято</h6>
                <p class="display-6 mb-0 text-success">{{ $callsAccepted }}</p>
                <small class="text-muted">длительность &gt; 1 сек</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Звонки пропущено</h6>
                <p class="display-6 mb-0 text-warning">{{ $callsMissed }}</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Сделки: залог</h6>
                <p class="display-6 mb-0 text-primary">{{ $convertedPawn }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Сделки: скупка</h6>
                <p class="display-6 mb-0 text-success">{{ $convertedPurchase }}</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Конверсия</h6>
                <p class="display-6 mb-0">{{ $conversionRate }}%</p>
                <small class="text-muted">{{ $totalDeals }} из {{ $totalContacts }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted">Сделки: комиссия</h6>
                <p class="display-6 mb-0">{{ $convertedCommission }}</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">По каналам</div>
            <div class="card-body">
                @php
                    $channelLabels = \App\Models\CallCenterContact::CHANNELS;
                @endphp
                @forelse($byChannel as $ch => $cnt)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $channelLabels[$ch] ?? $ch }}</span>
                        <strong>{{ $cnt }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">Нет данных</p>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">По исходам</div>
            <div class="card-body">
                @php
                    $outcomeLabels = \App\Models\CallCenterContact::OUTCOMES;
                @endphp
                @forelse($byOutcome as $out => $cnt)
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>{{ $outcomeLabels[$out] ?? $out }}</span>
                        <strong>{{ $cnt }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">Нет данных</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="mb-3">
    <a href="{{ route('call-center.index') }}" class="btn btn-secondary">← К списку обращений</a>
</div>
@endsection
