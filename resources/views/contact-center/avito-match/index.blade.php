@extends('layouts.app')

@section('title', 'Avito: активные объявления ↔ витрина')

@section('content')
<div class="mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="{{ route('section.contact-center') }}">Контакт центр</a></li>
            <li class="breadcrumb-item active">Avito ↔ витрина</li>
        </ol>
    </nav>
    <h1 class="h4 mb-0">Avito: активные объявления ↔ витрина</h1>
    <p class="text-muted small mb-0">Загрузите выгрузку активных объявлений из Avito (или заберите по API) — сопоставим по названию с товарами точки по выбранному статусу.</p>
</div>

@if(session('error'))
    <div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif
@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <form method="post" action="{{ route('contact-center.avito-match.upload') }}" enctype="multipart/form-data" class="col-12 col-lg-9">
            @csrf
            <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1">Точка (инвентаризация)</label>
                <select name="store_id" class="form-select form-select-sm" required>
                    @foreach($stores as $s)
                        <option value="{{ $s->id }}" @selected((int) old('store_id', $defaultStoreId) === $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-1">Статус товара</label>
                <select name="status_id" class="form-select form-select-sm" required>
                    @foreach($statuses as $st)
                        <option value="{{ $st->id }}" @selected((int) old('status_id', $defaultStatusId) === $st->id)>{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Файл Avito (xlsx/xls/csv)</label>
                <input type="file" name="file" class="form-control form-control-sm" required accept=".xlsx,.xls,.csv,.txt">
                @error('file')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-sm btn-primary" name="source" value="file"><i class="bi bi-upload"></i> Сопоставить по файлу</button>
            </div>
            </div>
        </form>

        <form method="post" action="{{ route('contact-center.avito-match.upload') }}" class="col-12 col-lg-3">
            @csrf
            <input type="hidden" name="store_id" value="{{ (int) old('store_id', $defaultStoreId) }}">
            <input type="hidden" name="status_id" value="{{ (int) old('status_id', $defaultStatusId) }}">
            <button type="submit" class="btn btn-sm btn-outline-primary w-100" name="source" value="api" title="Забрать активные объявления через Avito API">
                <i class="bi bi-cloud-download"></i> Загрузить по API
            </button>
        </form>
        </div>
    </div>
</div>

@if($results && !empty($results['warnings']))
    <div class="alert alert-warning py-2">
        <strong>Предупреждения:</strong>
        <ul class="mb-0">
            @foreach($results['warnings'] as $w)
                <li>{{ $w }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($results && !empty($results['matches']))
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div class="fw-semibold">Результат сопоставления</div>
            <div class="text-muted small">Товаров в витрине: {{ number_format((int) ($results['items_count'] ?? 0), 0, ',', ' ') }}</div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Avito объявление</th>
                        <th class="text-end">Цена</th>
                        <th>Лучший матч (портал)</th>
                        <th class="text-center">Скор</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($results['matches'] as $m)
                        @php
                            $ad = $m['ad'];
                            $best = $m['best'];
                            $score = $best['score'] ?? 0;
                        @endphp
                        <tr class="{{ $score >= 80 ? '' : ($score >= 65 ? 'table-warning' : 'table-danger') }}">
                            <td>
                                <div class="fw-semibold">{{ $ad['title'] ?? '—' }}</div>
                                <div class="small text-muted">
                                    @if(!empty($ad['status']))Статус: {{ $ad['status'] }} @endif
                                    @if(!empty($ad['id'])) · ID: {{ $ad['id'] }} @endif
                                    @if(!empty($ad['url'])) · <a href="{{ $ad['url'] }}" target="_blank" rel="noopener">ссылка</a> @endif
                                </div>
                            </td>
                            <td class="text-end">
                                @if(isset($ad['price']))
                                    {{ number_format((float) $ad['price'], 0, ',', ' ') }} ₽
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if($best)
                                    <a href="{{ route('items.show', $best['item_id']) }}" class="text-decoration-none fw-semibold">{{ $best['item_name'] }}</a>
                                    <div class="small text-muted"><code>{{ $best['barcode'] }}</code>@if(isset($best['current_price'])) · {{ number_format((float) $best['current_price'], 0, ',', ' ') }} ₽ @endif</div>
                                @else
                                    <span class="text-muted">Не найдено</span>
                                @endif
                                @if(!empty($m['candidates']) && count($m['candidates']) > 1)
                                    <details class="mt-1">
                                        <summary class="small text-muted">Показать кандидатов</summary>
                                        <ul class="small mb-0">
                                            @foreach($m['candidates'] as $c)
                                                <li>
                                                    <a href="{{ route('items.show', $c['item_id']) }}">{{ $c['item_name'] }}</a>
                                                    ({{ $c['score'] }}%)
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge rounded-pill {{ $score >= 80 ? 'text-bg-success' : ($score >= 65 ? 'text-bg-warning' : 'text-bg-danger') }}">{{ $score }}%</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white small text-muted">
            Скор — похожесть названий (0–100). Если много строк подсвечено красным, лучше добавить в текст Avito наш штрихкод — тогда сопоставление будет почти 100%.
        </div>
    </div>
@endif
@endsection

