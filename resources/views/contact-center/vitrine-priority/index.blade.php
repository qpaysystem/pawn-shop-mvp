@extends('layouts.app')

@section('title', 'Витрина к продаже')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="{{ route('section.contact-center') }}">Контакт центр</a></li>
                <li class="breadcrumb-item active">Витрина к продаже</li>
            </ol>
        </nav>
        <h1 class="h4 mb-0">Витрина к продаже — приоритет</h1>
        <p class="text-muted small mb-0">
            Товары на витрине: комиссия и скупка (не проданы).
            Порог залежалости: <strong>{{ $staleDays }} дней</strong>. Основной канал — Avito.
        </p>
    </div>
    <div class="d-flex gap-2">
        <form method="post" action="{{ route('contact-center.vitrine-priority.sync-avito') }}" class="d-inline"
            onsubmit="return confirm('Загрузить все обращения Avito по активным объявлениям? Это может занять 1–3 минуты.');">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm">
                <i class="bi bi-cloud-download"></i> Загрузить Avito
            </button>
        </form>
        <a href="{{ route('call-center.index', ['tab' => 'avito']) }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-chat-dots"></i> Avito
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger py-2">{{ session('error') }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Позиций на витрине</div>
                <div class="fs-4 fw-semibold">{{ number_format($totals['count'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Залежалые ({{ $staleDays }}+ дн.)</div>
                <div class="fs-4 fw-semibold text-warning">{{ number_format($totals['stale_count'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Залежалые без интереса</div>
                <div class="fs-4 fw-semibold text-danger">{{ number_format($totals['low_interest_stale_count'], 0, ',', ' ') }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Сумма витрины</div>
                <div class="fs-5 fw-semibold">{{ number_format($totals['amount'], 0, ',', ' ') }} ₽</div>
            </div>
        </div>
    </div>
</div>

<form method="get" class="card border-0 shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Поиск</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Название, штрихкод, договор...">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Точка</label>
                <select name="store_id" class="form-select form-select-sm">
                    <option value="">Все точки</option>
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" @selected((int) request('store_id') === $store->id)>{{ $store->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="stale_only" value="1" id="stale_only" @checked(request()->boolean('stale_only'))>
                    <label class="form-check-label small" for="stale_only">Только залежалые ({{ $staleDays }}+ дней)</label>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary">Применить</button>
                <a href="{{ route('contact-center.vitrine-priority.index') }}" class="btn btn-sm btn-outline-secondary">Сбросить</a>
            </div>
        </div>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:48px"></th>
                    <th>Товар</th>
                    <th>Точка</th>
                    <th class="text-end">Дней</th>
                    <th class="text-end">Цена</th>
                    <th class="text-end">Оценка</th>
                    <th class="text-center">Интерес</th>
                    <th class="text-center">Приор.</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr class="{{ $row['is_stale'] && $row['interest_count'] === 0 ? 'table-warning' : '' }}">
                    <td>
                        @if($row['photo_url'])
                            <img src="{{ $row['photo_url'] }}" alt="" class="rounded" style="width:40px;height:40px;object-fit:cover;">
                        @else
                            <span class="d-inline-flex align-items-center justify-content-center rounded bg-light text-muted" style="width:40px;height:40px;"><i class="bi bi-image"></i></span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('items.show', $row['item_id']) }}" class="fw-semibold text-decoration-none">{{ $row['name'] }}</a>
                        <div class="small text-muted">
                            <code>{{ $row['barcode'] }}</code>
                            @if($row['contract_number'])
                                · {{ $row['contract_number'] }} @if(!empty($row['contract_label'])) ({{ $row['contract_label'] }}) @endif
                            @endif
                            @if($row['category_name']) · {{ $row['category_name'] }} @endif
                        </div>
                        @if($row['has_active_reservation'])
                            <span class="badge text-bg-info">Есть бронь</span>
                        @endif
                        @if($row['is_stale'])
                            <span class="badge text-bg-warning">Залежалый</span>
                        @endif
                    </td>
                    <td class="small">{{ $row['store_name'] }}</td>
                    <td class="text-end">
                        <span class="{{ $row['is_stale'] ? 'text-danger fw-semibold' : '' }}">{{ $row['days_on_vitrine'] }}</span>
                        <div class="text-muted small">с {{ $row['listed_at'] }}</div>
                    </td>
                    <td class="text-end">
                        <strong>{{ number_format($row['current_price'], 0, ',', ' ') }} ₽</strong>
                        @if($row['price_vs_market_pct'] !== null && $row['price_vs_market_pct'] > 0)
                            <div class="small text-danger">+{{ $row['price_vs_market_pct'] }}% к оценке</div>
                        @endif
                    </td>
                    <td class="text-end text-muted small">
                        @if($row['market_price'])
                            {{ number_format($row['market_price'], 0, ',', ' ') }} ₽
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-center small">
                        <div>{{ $row['leads_count'] }} заяв.</div>
                        <div class="text-muted">{{ $row['reservations_count'] }} брон.</div>
                        @if($row['avito_inquiries_count'] > 0)
                            <div class="text-warning fw-semibold" title="Входящие сообщения Avito по объявлению">
                                <i class="bi bi-chat-dots"></i> {{ $row['avito_inquiries_count'] }} Avito
                            </div>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="badge rounded-pill {{ $row['priority_score'] >= 60 ? 'text-bg-danger' : ($row['priority_score'] >= 40 ? 'text-bg-warning' : 'text-bg-secondary') }}">
                            {{ $row['priority_score'] }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('contact-center.leads.create', ['type' => 'sale_request', 'item_id' => $row['item_id']]) }}" class="btn btn-sm btn-outline-primary" title="Заявка на продажу">
                            <i class="bi bi-inbox"></i>
                        </a>
                        @if($canDiscount)
                        <button type="button" class="btn btn-sm btn-outline-success btn-discount"
                            data-bs-toggle="modal" data-bs-target="#discountModal"
                            data-item-id="{{ $row['item_id'] }}"
                            data-item-name="{{ $row['name'] }}"
                            data-current-price="{{ $row['current_price'] }}"
                            title="Скидка">
                            <i class="bi bi-percent"></i>
                        </button>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">Нет комиссионных товаров на витрине по выбранным фильтрам.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($canDiscount)
<div class="modal fade" id="discountModal" tabindex="-1" aria-labelledby="discountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" id="discountForm" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="discountModalLabel">Скидка на витрине</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong id="discountItemName"></strong></p>
                <p class="text-muted small mb-3">Текущая цена: <span id="discountCurrentPrice"></span> ₽</p>

                <div class="mb-3">
                    <label class="form-label">Скидка, %</label>
                    <div class="input-group input-group-sm" style="max-width:200px;">
                        <input type="number" id="discountPercent" class="form-control" min="1" max="90" step="1" placeholder="10">
                        <button type="button" class="btn btn-outline-secondary" id="applyPercentBtn">Применить</button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="new_price">Новая цена, ₽</label>
                    <input type="number" name="new_price" id="new_price" class="form-control" required min="1" step="1">
                </div>

                <div class="mb-0">
                    <label class="form-label" for="reason">Комментарий</label>
                    <input type="text" name="reason" id="reason" class="form-control" placeholder="Для Avito, акция, договорённость с клиентом...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="submit" class="btn btn-success">Сохранить скидку</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if($canDiscount)
<script>
(function () {
    var modal = document.getElementById('discountModal');
    if (!modal) return;

    var form = document.getElementById('discountForm');
    var currentPrice = 0;

    modal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        currentPrice = parseFloat(btn.getAttribute('data-current-price')) || 0;
        form.action = '{{ url('contact-center/vitrine-priority') }}/' + btn.getAttribute('data-item-id') + '/discount';
        document.getElementById('discountItemName').textContent = btn.getAttribute('data-item-name');
        document.getElementById('discountCurrentPrice').textContent = currentPrice.toLocaleString('ru-RU');
        document.getElementById('new_price').value = '';
        document.getElementById('discountPercent').value = '';
        document.getElementById('reason').value = '';
    });

    document.getElementById('applyPercentBtn').addEventListener('click', function () {
        var pct = parseFloat(document.getElementById('discountPercent').value);
        if (!pct || pct <= 0 || pct >= 100 || !currentPrice) return;
        var newPrice = Math.max(1, Math.round(currentPrice * (1 - pct / 100)));
        document.getElementById('new_price').value = newPrice;
    });
})();
</script>
@endif
@endpush
