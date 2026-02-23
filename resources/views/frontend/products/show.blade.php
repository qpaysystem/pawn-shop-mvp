@extends('layouts.frontend')
@section('title', $product->name)
@section('content')
<div class="row">
    <div class="col-md-4">
        @if($product->photo_path)
            <img src="{{ asset('storage/'.$product->photo_path) }}" alt="{{ $product->name }}" class="img-fluid rounded shadow-sm">
        @else
            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 280px;">
                <i class="bi bi-box-seam display-1 text-muted"></i>
            </div>
        @endif
    </div>
    <div class="col-md-8">
        <h1 class="h4 mb-3">
        {{ $product->name }}
        @if($product->isPledge())<span class="badge bg-warning text-dark">Залог</span>@endif
    </h1>
        <dl class="row mb-0">
            @if($product->kind)
                <dt class="col-sm-4 text-muted">Вид</dt>
                <dd class="col-sm-8">{{ $product->kind }}</dd>
            @endif
            @if($product->type)
                <dt class="col-sm-4 text-muted">Тип</dt>
                <dd class="col-sm-8">{{ $product->type }}</dd>
            @endif
            @if($product->estimated_cost !== null)
                <dt class="col-sm-4 text-muted">Оценочная стоимость</dt>
                <dd class="col-sm-8"><strong>{{ number_format($product->estimated_cost, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</strong></dd>
            @endif
        </dl>
        @if($product->description)
            <hr>
            <p class="text-muted">{{ nl2br(e($product->description)) }}</p>
        @endif
        <a href="{{ route('frontend.products.index') }}" class="btn btn-outline-secondary mt-3">← К списку товаров</a>
    </div>
</div>
@endsection
