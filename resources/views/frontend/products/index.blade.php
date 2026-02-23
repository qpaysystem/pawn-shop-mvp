@extends('layouts.frontend')
@section('title', 'Товары')
@section('content')
<h1 class="h4 mb-4">Товары</h1>

<div class="row g-3">
    @forelse($products as $p)
    <div class="col-md-6 col-lg-4">
        <div class="card h-100">
            @if($p->photo_path)
                <img src="{{ asset('storage/'.$p->photo_path) }}" class="card-img-top" alt="{{ $p->name }}" style="height: 200px; object-fit: cover;">
            @else
                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                    <i class="bi bi-box-seam display-4 text-muted"></i>
                </div>
            @endif
            <div class="card-body">
                <h5 class="card-title">
                    {{ $p->name }}
                    @if($p->isPledge())<span class="badge bg-warning text-dark ms-1">Залог</span>@endif
                </h5>
                @if($p->kind || $p->type)
                    <p class="card-text text-muted small mb-1">
                        @if($p->kind) {{ $p->kind }} @endif
                        @if($p->kind && $p->type) · @endif
                        @if($p->type) {{ $p->type }} @endif
                    </p>
                @endif
                @if($p->estimated_cost !== null)
                    <p class="card-text mb-1">Оценочная стоимость: <strong>{{ number_format($p->estimated_cost, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</strong></p>
                @endif
                @if($p->description)
                    <p class="card-text small">{{ Str::limit($p->description, 100) }}</p>
                @endif
                <a href="{{ route('frontend.products.show', $p) }}" class="btn btn-outline-primary btn-sm">Подробнее</a>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12"><p class="text-muted">Товары не найдены.</p></div>
    @endforelse
</div>

<div class="mt-4">{{ $products->links() }}</div>
@endsection
