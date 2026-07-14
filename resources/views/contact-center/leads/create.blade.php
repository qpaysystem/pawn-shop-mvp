@extends('layouts.app')

@section('title', 'Новая заявка')

@section('content')
<div class="mb-3">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
            <li class="breadcrumb-item"><a href="{{ route('section.contact-center') }}">Контакт центр</a></li>
            <li class="breadcrumb-item"><a href="{{ route('contact-center.leads.index') }}">Заявки</a></li>
            <li class="breadcrumb-item active">Новая</li>
        </ol>
    </nav>
    <h1 class="h4 mb-0">Новая заявка</h1>
</div>

<form method="post" action="{{ route('contact-center.leads.store') }}" id="lead-form">
    @csrf
    @if($presetContact)
        <input type="hidden" name="call_center_contact_id" value="{{ $presetContact->id }}">
        <div class="alert alert-info py-2">
            Из обращения: {{ $presetContact->channel_label }},
            {{ $presetContact->contact_name ?? $presetContact->client?->full_name }}
        </div>
    @else
        <input type="hidden" name="call_center_contact_id" id="call_center_contact_id" value="{{ old('call_center_contact_id') }}">
    @endif
    @if($presetItem)
        <div class="alert alert-secondary py-2">
            Товар с витрины: <strong>{{ $presetItem->name }}</strong>
            @if($presetItem->barcode) · <code>{{ $presetItem->barcode }}</code> @endif
            @if($presetItem->current_price) · {{ number_format((float) $presetItem->current_price, 0, ',', ' ') }} ₽ @endif
        </div>
    @endif

    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-header bg-white">Тип и канал</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Тип заявки *</label>
                    <select name="type" id="lead_type" class="form-select" required>
                        @foreach($types as $k => $label)
                            <option value="{{ $k }}" @selected(old('type', $presetType ?? 'estimate') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Канал *</label>
                    <select name="source_channel" id="source_channel" class="form-select" required>
                        @foreach($channels as $k => $label)
                            <option value="{{ $k }}" @selected(old('source_channel', $presetContact?->channel ?? ($presetItem ? 'avito' : 'phone')) === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Предпочтительная дата визита</label>
                    <input type="datetime-local" name="preferred_at" class="form-control" value="{{ old('preferred_at') }}">
                </div>
            </div>
        </div>
    </div>

    @if(! $presetContact)
    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-header bg-white">Привязать к обращению</div>
        <div class="card-body">
            <div class="mb-2">
                <label class="form-label">Поиск обращения по выбранному каналу</label>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control" id="contact_search" placeholder="Телефон, имя, ID...">
                    <button type="button" class="btn btn-outline-primary flex-shrink-0" id="contact_pick_btn" data-bs-toggle="modal" data-bs-target="#contactPickModal">
                        Выбрать из списка
                    </button>
                </div>
                <div id="contact_search_results" class="list-group mt-1" style="max-height:180px;overflow:auto;display:none;"></div>
                <div class="form-text">Обращения подтягиваются из звонков/Telegram/Avito (после открытия чата в разделе Avito).</div>
            </div>
            <div id="contact_selected" class="small text-success"></div>
        </div>
    </div>

    <div class="modal fade" id="contactPickModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Выбор обращения</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">Поиск</label>
                        <input type="text" class="form-control" id="contact_modal_search" placeholder="Телефон, имя, текст...">
                        <div class="form-text">Показываем последние обращения по выбранному каналу. Для фильтрации начните печатать.</div>
                    </div>
                    <div class="list-group" id="contact_modal_list"></div>
                    <div class="d-flex justify-content-center mt-3">
                        <button type="button" class="btn btn-outline-secondary" id="contact_modal_more" style="display:none;">Показать ещё</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-header bg-white">Клиент</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Поиск клиента</label>
                <input type="text" class="form-control" id="client_search" placeholder="Телефон или ФИО...">
                <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id', $presetContact?->client_id) }}">
                <div id="client_search_results" class="list-group mt-1" style="max-height:150px;overflow:auto;display:none;"></div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Имя / ФИО</label>
                    <input type="text" name="contact_name" class="form-control" id="contact_name"
                        value="{{ old('contact_name', $presetContact?->contact_name ?? $presetContact?->client?->full_name) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Телефон</label>
                    <input type="text" name="contact_phone" class="form-control" id="contact_phone"
                        value="{{ old('contact_phone', $presetContact?->contact_phone ?? $presetContact?->client?->phone) }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3 border-0 shadow-sm" id="sale-item-block" style="display:none;">
        <div class="card-header bg-white">Товар (для заявки на продажу)</div>
        <div class="card-body">
            <label class="form-label">Поиск товара</label>
            <input type="text" class="form-control" id="item_search" placeholder="Штрихкод, номер, название...">
            <input type="hidden" name="item_id" id="item_id" value="{{ old('item_id', $presetItem?->id) }}">
            <div id="item_search_results" class="list-group mt-1" style="max-height:150px;overflow:auto;display:none;"></div>
            <div id="item_selected" class="small text-success mt-2">@if($presetItem)Выбран: {{ $presetItem->name }}@endif</div>
        </div>
    </div>

    <div class="card mb-3 border-0 shadow-sm" id="items-block">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span>Позиции</span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-item-row">+ Позиция</button>
        </div>
        <div class="card-body" id="items-rows">
            <div class="item-row border rounded p-3 mb-2">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">Название</label>
                        <input type="text" name="items[0][title]" class="form-control" placeholder="Кольцо золотое...">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Ожидаемая цена</label>
                        <input type="number" step="0.01" name="items[0][expected_price]" class="form-control">
                    </div>
                    <div class="col-md-3 mb-2">
                        <label class="form-label">Оценка от–до</label>
                        <div class="input-group">
                            <input type="number" step="0.01" name="items[0][appraised_from]" class="form-control" placeholder="от">
                            <input type="number" step="0.01" name="items[0][appraised_to]" class="form-control" placeholder="до">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Описание</label>
                        <textarea name="items[0][description]" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-header bg-white">Назначение и заметки</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Целевая точка</label>
                    <select name="store_id_target" class="form-select">
                        <option value="">— позже —</option>
                        @foreach($stores as $s)
                            <option value="{{ $s->id }}" @selected(old('store_id_target', $presetItem?->store_id) == $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mb-0">
                <label class="form-label">Заметки оператора</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes', $presetContact?->notes) }}</textarea>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Создать заявку</button>
    <a href="{{ route('contact-center.leads.index') }}" class="btn btn-secondary">Отмена</a>
</form>

@push('scripts')
<script>
(function() {
    var itemIndex = 1;
    var leadType = document.getElementById('lead_type');
    var saleBlock = document.getElementById('sale-item-block');
    var itemsBlock = document.getElementById('items-block');

    function toggleTypeBlocks() {
        var isSale = leadType.value === 'sale_request';
        saleBlock.style.display = isSale ? 'block' : 'none';
        itemsBlock.style.display = isSale ? 'none' : 'block';

        // Чтобы сервер не ругался на пустые items[0][title] при скрытом блоке — отключаем поля.
        var itemInputs = itemsBlock.querySelectorAll('input, textarea, select');
        itemInputs.forEach(function(el) { el.disabled = isSale; });

        var saleInputs = saleBlock.querySelectorAll('input, textarea, select');
        saleInputs.forEach(function(el) { el.disabled = !isSale; });
    }
    leadType.addEventListener('change', toggleTypeBlocks);
    toggleTypeBlocks();

    @if($presetType)
    leadType.value = @json($presetType);
    toggleTypeBlocks();
    @endif

    document.getElementById('add-item-row').addEventListener('click', function() {
        var row = document.createElement('div');
        row.className = 'item-row border rounded p-3 mb-2';
        row.innerHTML = '<div class="row">' +
            '<div class="col-md-6 mb-2"><label class="form-label">Название</label><input type="text" name="items[' + itemIndex + '][title]" class="form-control"></div>' +
            '<div class="col-md-3 mb-2"><label class="form-label">Ожидаемая цена</label><input type="number" step="0.01" name="items[' + itemIndex + '][expected_price]" class="form-control"></div>' +
            '<div class="col-md-3 mb-2"><label class="form-label">Оценка от–до</label><div class="input-group"><input type="number" step="0.01" name="items[' + itemIndex + '][appraised_from]" class="form-control" placeholder="от"><input type="number" step="0.01" name="items[' + itemIndex + '][appraised_to]" class="form-control" placeholder="до"></div></div>' +
            '<div class="col-12"><label class="form-label">Описание</label><textarea name="items[' + itemIndex + '][description]" class="form-control" rows="2"></textarea></div>' +
            '</div>';
        document.getElementById('items-rows').appendChild(row);
        itemIndex++;
    });

    function bindSearch(inputId, resultsId, hiddenId, url, onPick) {
        var input = document.getElementById(inputId);
        var results = document.getElementById(resultsId);
        var hidden = document.getElementById(hiddenId);
        var timer;
        input.addEventListener('input', function() {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 2) { results.style.display = 'none'; return; }
            timer = setTimeout(function() {
                fetch(url + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        results.innerHTML = '';
                        data.forEach(function(item) {
                            var a = document.createElement('a');
                            a.href = '#';
                            a.className = 'list-group-item list-group-item-action';
                            a.textContent = item.full_name ? (item.full_name + ' — ' + (item.phone || '')) : item.label;
                            a.onclick = function(e) {
                                e.preventDefault();
                                hidden.value = item.id;
                                if (onPick) onPick(item);
                                results.style.display = 'none';
                            };
                            results.appendChild(a);
                        });
                        results.style.display = data.length ? 'block' : 'none';
                    });
            }, 300);
        });
    }

    bindSearch('client_search', 'client_search_results', 'client_id', '{{ route("clients.search") }}', function(c) {
        document.getElementById('contact_name').value = c.full_name || '';
        document.getElementById('contact_phone').value = c.phone || '';
        document.getElementById('client_search').value = c.full_name || '';
    });

    bindSearch('item_search', 'item_search_results', 'item_id', '{{ route("contact-center.leads.items.search") }}', function(item) {
        document.getElementById('item_selected').textContent = 'Выбран: ' + item.label + (item.store ? ' (' + item.store + ')' : '');
        document.getElementById('item_search').value = item.label;
    });

    @if(! $presetContact)
    function bindContactSearch() {
        var input = document.getElementById('contact_search');
        var results = document.getElementById('contact_search_results');
        var hidden = document.getElementById('call_center_contact_id');
        var selected = document.getElementById('contact_selected');
        var channelSelect = document.getElementById('source_channel');
        var timer;

        if (!input || !results || !hidden || !channelSelect) return;

        function fetchContacts(q) {
            var channel = channelSelect.value;
            if (q.length < 2) { results.style.display = 'none'; return; }
            timer = setTimeout(function() {
                fetch('{{ route("contact-center.contacts.search") }}?channel=' + encodeURIComponent(channel) + '&q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        results.innerHTML = '';
                        data.forEach(function(c) {
                            var a = document.createElement('a');
                            a.href = '#';
                            a.className = 'list-group-item list-group-item-action';
                            a.textContent = c.label;
                            a.onclick = function(e) {
                                e.preventDefault();
                                hidden.value = c.id;
                                selected.innerHTML = 'Выбрано обращение: <a href=\"' + c.url + '\" target=\"_blank\">#' + c.id + '</a> — ' + c.label;
                                results.style.display = 'none';
                                if (c.client) {
                                    document.getElementById('client_id').value = c.client.id || '';
                                    document.getElementById('contact_name').value = c.client.full_name || '';
                                    document.getElementById('contact_phone').value = c.client.phone || '';
                                    document.getElementById('client_search').value = c.client.full_name || '';
                                } else {
                                    if (c.contact_name) document.getElementById('contact_name').value = c.contact_name;
                                    if (c.contact_phone) document.getElementById('contact_phone').value = c.contact_phone;
                                }
                            };
                            results.appendChild(a);
                        });
                        results.style.display = data.length ? 'block' : 'none';
                    });
            }, 300);
        }

        input.addEventListener('input', function() {
            clearTimeout(timer);
            fetchContacts(this.value.trim());
        });
        channelSelect.addEventListener('change', function() {
            hidden.value = '';
            selected.textContent = '';
            results.style.display = 'none';
            input.value = '';
        });
    }

    bindContactSearch();

    // Modal picker: recent contacts with optional search.
    (function() {
        var modalEl = document.getElementById('contactPickModal');
        if (!modalEl) return;
        var listEl = document.getElementById('contact_modal_list');
        var searchEl = document.getElementById('contact_modal_search');
        var moreBtn = document.getElementById('contact_modal_more');
        var channelSelect = document.getElementById('source_channel');
        var hidden = document.getElementById('call_center_contact_id');
        var selected = document.getElementById('contact_selected');

        var nextOffset = 0;
        var loading = false;
        var lastQuery = '';

        function pickContact(c) {
            hidden.value = c.id;
            selected.innerHTML = 'Выбрано обращение: <a href="' + c.url + '" target="_blank">#' + c.id + '</a> — ' + c.label;
            if (c.client) {
                document.getElementById('client_id').value = c.client.id || '';
                document.getElementById('contact_name').value = c.client.full_name || '';
                document.getElementById('contact_phone').value = c.client.phone || '';
                document.getElementById('client_search').value = c.client.full_name || '';
            } else {
                if (c.contact_name) document.getElementById('contact_name').value = c.contact_name;
                if (c.contact_phone) document.getElementById('contact_phone').value = c.contact_phone;
            }
        }

        function renderItems(items, append) {
            if (!append) listEl.innerHTML = '';
            items.forEach(function(c) {
                var a = document.createElement('a');
                a.href = '#';
                a.className = 'list-group-item list-group-item-action';
                a.textContent = c.label;
                a.onclick = function(e) {
                    e.preventDefault();
                    pickContact(c);
                    var inst = bootstrap.Modal.getInstance(modalEl);
                    if (inst) inst.hide();
                };
                listEl.appendChild(a);
            });
        }

        function loadRecent(reset) {
            if (loading) return;
            loading = true;
            moreBtn.style.display = 'none';
            var channel = channelSelect.value;
            var q = (searchEl.value || '').trim();

            if (reset) {
                nextOffset = 0;
                lastQuery = q;
            }

            // If user types >=2 chars, reuse /search; else use /recent.
            var useSearch = q.length >= 2;
            var url = useSearch
                ? ('{{ route("contact-center.contacts.search") }}?channel=' + encodeURIComponent(channel) + '&q=' + encodeURIComponent(q))
                : ('{{ route("contact-center.contacts.recent") }}?channel=' + encodeURIComponent(channel) + '&limit=30&offset=' + nextOffset);

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (useSearch) {
                        renderItems(data || [], false);
                        moreBtn.style.display = 'none';
                    } else {
                        renderItems((data.items || []), nextOffset > 0);
                        nextOffset = data.next_offset || (nextOffset + (data.items || []).length);
                        moreBtn.style.display = data.has_more ? 'inline-block' : 'none';
                    }
                })
                .finally(function() {
                    loading = false;
                });
        }

        modalEl.addEventListener('shown.bs.modal', function() {
            searchEl.value = '';
            loadRecent(true);
        });
        moreBtn.addEventListener('click', function() { loadRecent(false); });

        var searchTimer;
        searchEl.addEventListener('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() { loadRecent(true); }, 250);
        });
        channelSelect.addEventListener('change', function() {
            // Reset modal list on channel change (if modal open).
            if (modalEl.classList.contains('show')) {
                loadRecent(true);
            }
        });
    })();
    @endif
})();
</script>
@endpush
@endsection
