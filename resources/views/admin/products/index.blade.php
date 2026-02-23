@extends('layouts.admin')
@section('title', 'ТМЦ')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">ТМЦ — Товарно-материальные ценности</h1>
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary">Добавить товар</a>
</div>

<form method="get" action="{{ route('admin.products.index') }}" class="mb-3">
    <div class="input-group" style="max-width: 400px;">
        <input type="text" name="search" class="form-control" placeholder="Поиск по названию, виду, типу" value="{{ request('search') }}">
        <button type="submit" class="btn btn-secondary">Найти</button>
    </div>
</form>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 80px;">Фото</th>
                    <th>Название</th>
                    <th>Вид</th>
                    <th>Тип</th>
                    <th class="text-end">Оценочная стоимость</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $p)
                <tr>
                    <td>
                        @if($p->photo_path)
                            <img src="{{ asset('storage/'.$p->photo_path) }}" alt="" class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                        @else
                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;"><i class="bi bi-image text-muted"></i></div>
                        @endif
                    </td>
                    <td>
                        {{ Str::limit($p->name, 50) }}
                        @if($p->isPledge())<span class="badge bg-warning text-dark ms-1">Залог</span>@endif
                    </td>
                    <td>{{ $p->kind ?? '—' }}</td>
                    <td>{{ $p->type ?? '—' }}</td>
                    <td class="text-end">{{ $p->estimated_cost !== null ? number_format($p->estimated_cost, 2) . ' ' . \App\Models\Setting::get('currency', 'RUB') : '—' }}</td>
                    <td>
                        <a href="{{ route('admin.products.edit', $p) }}" class="btn btn-sm btn-outline-primary">Изменить</a>
                        <form method="post" action="{{ route('admin.products.destroy', $p) }}" class="d-inline" onsubmit="return confirm('Удалить товар?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-muted">Нет товаров</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($products->hasPages())
        <div class="card-footer">{{ $products->links() }}</div>
    @endif
</div>
@endsection
