@extends('layouts.app')

@section('title', 'Приём товара')

@section('content')
<h1 class="h4 mb-4">Приём товара</h1>

<ul class="nav nav-tabs mb-4" id="acceptTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-visit" data-bs-toggle="tab" data-bs-target="#pane-visit" type="button" role="tab">1. Оформление визита клиента</button>
    </li>
    <li class="nav-item d-none" role="presentation" id="tab-redemption-wrap">
        <button class="nav-link" id="tab-redemption" data-bs-toggle="tab" data-bs-target="#pane-redemption" type="button" role="tab">2. Оформление выкупа</button>
    </li>
    <li class="nav-item" role="presentation" id="tab-client-wrap">
        <button class="nav-link" id="tab-client" data-bs-toggle="tab" data-bs-target="#pane-client" type="button" role="tab">2. Идентификация клиента</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-item" data-bs-toggle="tab" data-bs-target="#pane-item" type="button" role="tab">3. Оценка товара</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-loan" data-bs-toggle="tab" data-bs-target="#pane-loan" type="button" role="tab">4. Выдача займа</button>
    </li>
</ul>

<form method="post" action="{{ route('accept.store') }}" enctype="multipart/form-data" id="accept-form">
    @csrf
    <input type="hidden" name="visit_purpose" id="visit_purpose_value" value="{{ old('visit_purpose', 'appraisal') }}">
    <div class="tab-content" id="acceptTabContent">
        {{-- Вкладка 1: Оформление визита клиента --}}
        <div class="tab-pane fade show active" id="pane-visit" role="tabpanel">
            <div class="card mb-4">
                <div class="card-header">Порядок действий</div>
                <div class="card-body">
                    <ol class="mb-0">
                        <li class="mb-3">
                            <strong>Создание события</strong><br>
                            <span class="text-muted">Событие: Личный визит клиента</span>
                        </li>
                        <li class="mb-3">
                            <label class="form-label fw-semibold">Выбор цели визита *</label>
                            <select id="visit_purpose" class="form-select" required aria-label="Цель визита">
                                @foreach(\App\Models\ClientVisit::purposeLabels() as $value => $label)
                                    <option value="{{ $value }}" {{ old('visit_purpose', 'appraisal') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </li>
                        <li>
                            <strong>Далее</strong> — оформление сделки, привязанной к событию (идентификация клиента → оценка товара → выдача займа).
                        </li>
                    </ol>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-primary" id="btn-visit-next">Далее: Идентификация клиента</button>
            </div>
        </div>

        {{-- Вкладка 2 (при цели «Выкуп»): Оформление выкупа --}}
        <div class="tab-pane fade" id="pane-redemption" role="tabpanel">
            <div class="card mb-4">
                <div class="card-header">Поиск клиента для выкупа</div>
                <div class="card-body">
                    <p class="text-muted small">Введите ФИО, номер телефона или номер договора залога.</p>
                    <div class="mb-3">
                        <label class="form-label">Поиск</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="redemption_search" placeholder="ФИО, телефон или № договора (например L-2024-00001)" autocomplete="off">
                            <button type="button" class="btn btn-primary" id="redemption_search_btn">Найти</button>
                        </div>
                    </div>
                    <div id="redemption_search_results" class="mt-3" style="display:none;">
                        <h6 class="mb-2">Результаты</h6>
                        <div id="redemption_clients_list"></div>
                    </div>
                    <div id="redemption_search_empty" class="alert alert-secondary mt-3" style="display:none;">Ничего не найдено. Уточните запрос.</div>
                    <div id="redemption_search_loading" class="mt-3" style="display:none;">Поиск…</div>
                </div>
            </div>
            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-tab-prev="pane-visit">Назад</button>
            </div>
        </div>

        {{-- Вкладка 2: Идентификация клиента --}}
        <div class="tab-pane fade" id="pane-client" role="tabpanel">
            <div class="card mb-4">
                <div class="card-header">Тип договора и магазин</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Тип договора *</label>
                        <select name="contract_type" class="form-select" id="contract_type" required>
                            <option value="pawn" {{ old('contract_type') === 'pawn' ? 'selected' : '' }}>Залог</option>
                            <option value="commission" {{ old('contract_type') === 'commission' ? 'selected' : '' }}>Комиссия</option>
                            <option value="purchase" {{ old('contract_type') === 'purchase' ? 'selected' : '' }}>Скупка</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Магазин *</label>
                        <select name="store_id" class="form-select" id="store_id" required>
                            @foreach($stores as $s)
                                <option value="{{ $s->id }}" {{ old('store_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header">Клиент</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Поиск клиента (телефон или ФИО)</label>
                        <input type="text" class="form-control" id="client_search" placeholder="Начните вводить..." autocomplete="off">
                        <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id') }}">
                        <div id="client_search_results" class="list-group mt-1" style="max-height:200px;overflow:auto;display:none;"></div>
                    </div>
                    <p class="text-muted small">или заполните данные нового клиента (ФИО — в блоке «Паспортные данные» после распознавания или вручную):</p>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Телефон *</label>
                            <input type="text" name="client_phone" id="client_phone" class="form-control" value="{{ old('client_phone') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="client_email" class="form-control" value="{{ old('client_email') }}">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-4">
                <div class="card-header">Паспортные данные</div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Фамилия *</label>
                            <input type="text" name="client_last_name" id="client_last_name" class="form-control" value="{{ old('client_last_name') }}" placeholder="Заполняется по фото или вручную">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Имя *</label>
                            <input type="text" name="client_first_name" id="client_first_name" class="form-control" value="{{ old('client_first_name') }}" placeholder="Заполняется по фото или вручную">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Отчество</label>
                            <input type="text" name="client_patronymic" id="client_patronymic" class="form-control" value="{{ old('client_patronymic') }}" placeholder="Опционально">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Дата рождения</label>
                            <input type="text" name="client_birth_date" id="client_birth_date" class="form-control" value="{{ old('client_birth_date') }}" placeholder="ДД.ММ.ГГГГ" autocomplete="off">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Серия и номер паспорта</label>
                            <input type="text" name="client_passport_series_number" id="client_passport_series_number" class="form-control" value="{{ old('client_passport_series_number') }}" placeholder="12 34 567890">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Кем выдан</label>
                            <input type="text" name="client_passport_issued_by" id="client_passport_issued_by" class="form-control" value="{{ old('client_passport_issued_by') }}" placeholder="Орган, выдавший паспорт">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Когда выдан</label>
                            <input type="text" name="client_passport_issued_at" id="client_passport_issued_at" class="form-control" value="{{ old('client_passport_issued_at') }}" placeholder="ДД.ММ.ГГГГ" autocomplete="off">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Паспортные данные (полный текст)</label>
                        <textarea name="client_passport" id="client_passport" class="form-control" rows="3" placeholder="Серия, номер, кем и когда выдан, регистрация…">{{ old('client_passport') }}</textarea>
                        <small class="text-muted">Заполняется автоматически после распознавания или вручную.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Фото паспорта</label>
                        <p class="text-muted small mb-1">Сфотографируйте ровно разворот с ФИО и данными (серия, номер, кем выдан). Хорошее освещение, без бликов и размытия — тогда распознавание будет точнее.</p>
                        <input type="file" id="passport_photo_input" class="form-control" accept="image/jpeg,image/png,image/jpg,image/webp" multiple>
                        <div id="passport_photo_previews" class="d-flex flex-wrap gap-2 mt-2"></div>
                    </div>
                    <button type="button" class="btn btn-outline-primary" id="passport_parse_btn" disabled>
                        <i class="bi bi-camera"></i> Распознать данные с фото
                    </button>
                    <span id="passport_parse_status" class="ms-2 small"></span>
                </div>
            </div>
            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-tab-prev="pane-visit">Назад</button>
                <button type="button" class="btn btn-primary" data-tab-next="pane-item">Далее: Оценка товара</button>
            </div>
        </div>

        {{-- Вкладка 3: Оценка товара --}}
        <div class="tab-pane fade" id="pane-item" role="tabpanel">
            <div class="card mb-4">
                <div class="card-header">Товар</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Название товара *</label>
                        <input type="text" name="item_name" id="item_name" class="form-control" value="{{ old('item_name') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Описание</label>
                        <textarea name="item_description" id="item_description" class="form-control" rows="2">{{ old('item_description') }}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Категория</label>
                            <select name="category_id" id="category_id" class="form-select">
                                <option value="">—</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}" {{ old('category_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Бренд</label>
                            <select name="brand_id" id="brand_id" class="form-select">
                                <option value="">—</option>
                                @foreach($brands as $b)
                                    <option value="{{ $b->id }}" {{ old('brand_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="card mb-3 border-primary" id="evaluation-method-block" style="display:none;">
                        <div class="card-header bg-light">Способ оценки</div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">Выберите способ оценки товара для категории «<span id="selected-category-name"></span>»:</p>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary" id="btn-ai-estimate">
                                    <i class="bi bi-robot"></i> Предложить оценку ИИ
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="btn-manual-estimate">
                                    <i class="bi bi-pencil"></i> Оценка вручную
                                </button>
                            </div>
                            <div id="ai-estimate-status" class="mt-2 small" style="display:none;"></div>
                            <div id="ai-estimate-result" class="mt-3" style="display:none;">
                                <div class="alert alert-success mb-3">
                                    <strong>Предложение ИИ:</strong>
                                    <div id="ai-explanation" class="mb-2"></div>
                                    <div class="small">Оценочная стоимость: <strong id="ai-initial-price"></strong> ₽. Сумма займа: <strong id="ai-loan-amount"></strong> ₽</div>
                                </div>
                                <div id="ai-avito-block" class="mb-3">
                                    <strong class="d-block mb-2"><i class="bi bi-link-45deg"></i> Похожие на Авито</strong>
                                    <a id="ai-avito-search-link" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary mb-2" style="display:none;">Поиск на Авито →</a>
                                    <div id="ai-avito-list" class="list-group list-group-flush"></div>
                                </div>
                                <div id="ai-images-block" style="display:none;">
                                    <strong class="d-block mb-2"><i class="bi bi-images"></i> Похожие фото</strong>
                                    <div id="ai-images-list" class="d-flex flex-wrap gap-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Место хранения</label>
                            <select name="storage_location_id" class="form-select" id="storage_location_id">
                                <option value="">—</option>
                                @foreach($storageLocations as $loc)
                                    <option value="{{ $loc->id }}" data-store-id="{{ $loc->store_id }}">{{ $loc->name }} ({{ $loc->store->name }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Статус товара *</label>
                            <select name="status_id" class="form-select" required>
                                @foreach($statuses as $s)
                                    <option value="{{ $s->id }}" {{ old('status_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row" id="price-fields-row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Оценочная стоимость</label>
                            <input type="number" name="initial_price" id="initial_price" class="form-control" step="0.01" min="0" value="{{ old('initial_price') }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Текущая цена (для продажи)</label>
                            <input type="number" name="current_price" id="current_price" class="form-control" step="0.01" min="0" value="{{ old('current_price') }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Фото (несколько файлов)</label>
                        <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary" data-tab-prev="pane-client">Назад</button>
                <button type="button" class="btn btn-primary" data-tab-next="pane-loan">Далее: Выдача займа</button>
            </div>
        </div>

        {{-- Вкладка 4: Выдача займа --}}
        <div class="tab-pane fade" id="pane-loan" role="tabpanel">
            <div class="card mb-4" id="block-pawn">
                <div class="card-header">Условия залога</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Сумма займа *</label>
                            <input type="number" name="loan_amount" id="loan_amount" class="form-control" step="0.01" min="0" value="{{ old('loan_amount') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Процент</label>
                            <input type="number" name="loan_percent" class="form-control" step="0.01" min="0" value="{{ old('loan_percent', 0) }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Дата займа *</label>
                            <input type="date" name="loan_date" class="form-control" value="{{ old('loan_date', date('Y-m-d')) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Срок залога до *</label>
                        <input type="date" name="expiry_date_pawn" class="form-control" value="{{ old('expiry_date_pawn') }}">
                    </div>
                </div>
            </div>
            <div class="card mb-4" id="block-commission" style="display:none;">
                <div class="card-header">Условия комиссии</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Цена продажи</label>
                            <input type="number" name="seller_price" class="form-control" step="0.01" min="0" value="{{ old('seller_price') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Комиссия %</label>
                            <input type="number" name="commission_percent" class="form-control" step="0.01" min="0" value="{{ old('commission_percent', 0) }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Срок до</label>
                            <input type="date" name="expiry_date_commission" class="form-control" value="{{ old('expiry_date_commission') }}">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card mb-4" id="block-purchase" style="display:none;">
                <div class="card-header">Условия скупки</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Сумма скупки *</label>
                            <input type="number" name="purchase_amount" id="purchase_amount" class="form-control" step="0.01" min="0" value="{{ old('purchase_amount') }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Дата скупки *</label>
                            <input type="date" name="purchase_date" id="purchase_date" class="form-control" value="{{ old('purchase_date', date('Y-m-d')) }}">
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary" data-tab-prev="pane-item">Назад</button>
                <button type="submit" class="btn btn-success">Принять товар и создать договор</button>
            </div>
        </div>
    </div>
</form>

<div class="mt-3">
    <a href="{{ route('dashboard') }}" class="btn btn-secondary">Отмена</a>
</div>

@push('scripts')
<script>
(function() {
    // Тип договора: показ блока залог/комиссия/скупка
    var contractType = document.getElementById('contract_type');
    var blockPawn = document.getElementById('block-pawn');
    var blockCommission = document.getElementById('block-commission');
    var blockPurchase = document.getElementById('block-purchase');
    function toggleContractBlocks() {
        var type = contractType.value;
        blockPawn.style.display = type === 'pawn' ? 'block' : 'none';
        blockCommission.style.display = type === 'commission' ? 'block' : 'none';
        blockPurchase.style.display = type === 'purchase' ? 'block' : 'none';
        document.getElementById('loan_amount').required = type === 'pawn';
        var purchaseAmount = document.getElementById('purchase_amount');
        var purchaseDate = document.getElementById('purchase_date');
        if (purchaseAmount) purchaseAmount.required = type === 'purchase';
        if (purchaseDate) purchaseDate.required = type === 'purchase';
    }
    contractType.addEventListener('change', toggleContractBlocks);
    toggleContractBlocks();

    // Блок способа оценки: показывать при выборе категории
    var categorySelect = document.getElementById('category_id');
    var evaluationBlock = document.getElementById('evaluation-method-block');
    var selectedCategoryName = document.getElementById('selected-category-name');
    var categoriesData = @json($categories->pluck('name', 'id'));
    function toggleEvaluationBlock() {
        var catId = categorySelect.value;
        if (catId && categoriesData[catId]) {
            evaluationBlock.style.display = 'block';
            selectedCategoryName.textContent = categoriesData[catId];
        } else {
            evaluationBlock.style.display = 'none';
            document.getElementById('ai-estimate-result').style.display = 'none';
            document.getElementById('ai-estimate-status').style.display = 'none';
        }
    }
    categorySelect.addEventListener('change', toggleEvaluationBlock);
    toggleEvaluationBlock();

    // Кнопка "Предложить оценку ИИ"
    document.getElementById('btn-ai-estimate').addEventListener('click', function() {
        var itemName = document.getElementById('item_name').value.trim();
        var categoryId = document.getElementById('category_id').value;
        if (!itemName) {
            alert('Укажите название товара перед запросом оценки.');
            return;
        }
        if (!categoryId) {
            alert('Выберите категорию.');
            return;
        }
        var brandSelect = document.getElementById('brand_id');
        var brandOption = brandSelect.options[brandSelect.selectedIndex];
        var brandName = (brandOption && brandOption.value) ? brandOption.text.trim() : '';

        var statusEl = document.getElementById('ai-estimate-status');
        var resultEl = document.getElementById('ai-estimate-result');
        statusEl.style.display = 'block';
        statusEl.className = 'mt-2 small text-muted';
        statusEl.textContent = 'Запрос к ИИ…';
        resultEl.style.display = 'none';
        this.disabled = true;

        var formData = new FormData();
        formData.append('_token', document.querySelector('input[name="_token"]').value);
        formData.append('item_name', itemName);
        formData.append('category_id', categoryId);
        formData.append('item_description', document.getElementById('item_description').value);
        formData.append('brand_name', brandName);

        fetch('{{ route("accept.ai-estimate") }}', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                document.getElementById('initial_price').value = data.initial_price || data.sale_price || '';
                document.getElementById('current_price').value = data.current_price || data.sale_price || '';
                document.getElementById('loan_amount').value = data.loan_amount || '';
                document.getElementById('ai-explanation').textContent = data.explanation || '—';
                document.getElementById('ai-initial-price').textContent = (data.initial_price || data.sale_price || 0).toLocaleString('ru-RU');
                document.getElementById('ai-loan-amount').textContent = (data.loan_amount || 0).toLocaleString('ru-RU');
                resultEl.style.display = 'block';

                var avitoBlock = document.getElementById('ai-avito-block');
                var avitoList = document.getElementById('ai-avito-list');
                var avitoSearchLink = document.getElementById('ai-avito-search-link');
                avitoList.innerHTML = '';
                var brandOpt = document.getElementById('brand_id').options[document.getElementById('brand_id').selectedIndex];
                var brandText = (brandOpt && brandOpt.value) ? brandOpt.text.trim() : '';
                var searchTerms = document.getElementById('item_name').value.trim();
                if (brandText && brandText !== '—') searchTerms += ' ' + brandText;
                var searchQ = encodeURIComponent(searchTerms);
                avitoSearchLink.href = 'https://www.avito.ru/rossiya?q=' + searchQ;
                avitoSearchLink.style.display = 'inline-block';
                if (data.avito_listings && data.avito_listings.length > 0) {
                    data.avito_listings.forEach(function(item) {
                        var a = document.createElement('a');
                        a.href = item.link;
                        a.target = '_blank';
                        a.rel = 'noopener';
                        a.className = 'list-group-item list-group-item-action py-2';
                        var title = (item.title || 'Объявление').replace(/[<>&"']/g, function(c) { return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'}[c]; });
                        var snip = (item.snippet || '').substring(0, 120).replace(/[<>&"']/g, function(c) { return {'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'}[c]; });
                        a.innerHTML = '<strong>' + title + '</strong><br><span class="small text-muted">' + snip + (item.snippet && item.snippet.length > 120 ? '…' : '') + '</span>';
                        avitoList.appendChild(a);
                    });
                }

                var imagesBlock = document.getElementById('ai-images-block');
                var imagesList = document.getElementById('ai-images-list');
                imagesList.innerHTML = '';
                if (data.similar_images && data.similar_images.length > 0) {
                    data.similar_images.forEach(function(img) {
                        var url = img.thumbnailUrl || img.imageUrl || '';
                        if (!url) return;
                        var a = document.createElement('a');
                        a.href = img.link || img.imageUrl || url;
                        a.target = '_blank';
                        a.rel = 'noopener';
                        a.className = 'd-block';
                        var safeUrl = url.replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        a.innerHTML = '<img src="' + safeUrl + '" alt="" class="rounded" style="width:100px;height:100px;object-fit:cover;border:1px solid #dee2e6;" loading="lazy" onerror="this.style.display=\'none\'">';
                        imagesList.appendChild(a);
                    });
                    imagesBlock.style.display = 'block';
                } else {
                    imagesBlock.style.display = 'none';
                }

                statusEl.textContent = 'Оценка получена. Значения подставлены в форму.';
                statusEl.className = 'mt-2 small text-success';
            } else {
                statusEl.textContent = data.error || 'Ошибка запроса';
                statusEl.className = 'mt-2 small text-danger';
            }
        })
        .catch(function(err) {
            statusEl.textContent = 'Ошибка сети: ' + (err.message || 'неизвестно');
            statusEl.className = 'mt-2 small text-danger';
        })
        .finally(function() {
            document.getElementById('btn-ai-estimate').disabled = false;
        });
    });

    document.getElementById('btn-manual-estimate').addEventListener('click', function() {
        document.getElementById('price-fields-row').scrollIntoView({ behavior: 'smooth' });
        document.getElementById('initial_price').focus();
    });

    // Места хранения по магазину
    var storeId = document.getElementById('store_id');
    var storageSelect = document.getElementById('storage_location_id');
    function filterStorage() {
        var sid = storeId.value;
        [].slice.call(storageSelect.options).forEach(function(opt) {
            if (opt.value === '') { opt.style.display = 'block'; return; }
            opt.style.display = (opt.getAttribute('data-store-id') === sid) ? 'block' : 'none';
        });
        if (!storageSelect.querySelector('option[value=""]')) return;
        var selectedStore = storageSelect.querySelector('option[value="' + storageSelect.value + '"]');
        if (selectedStore && selectedStore.getAttribute('data-store-id') !== sid) storageSelect.value = '';
    }
    storeId.addEventListener('change', filterStorage);
    filterStorage();

    // Поиск клиента
    var clientSearch = document.getElementById('client_search');
    var clientId = document.getElementById('client_id');
    var clientLastName = document.getElementById('client_last_name');
    var clientFirstName = document.getElementById('client_first_name');
    var clientPatronymic = document.getElementById('client_patronymic');
    var clientPhone = document.getElementById('client_phone');
    var resultsDiv = document.getElementById('client_search_results');
    var searchTimer;
    clientSearch.addEventListener('input', function() {
        clearTimeout(searchTimer);
        var q = this.value.trim();
        if (q.length < 2) { resultsDiv.style.display = 'none'; return; }
        searchTimer = setTimeout(function() {
            fetch('{{ route("clients.search") }}?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    resultsDiv.innerHTML = '';
                    data.forEach(function(c) {
                        var a = document.createElement('a');
                        a.href = '#';
                        a.className = 'list-group-item list-group-item-action';
                        a.textContent = c.full_name + ' — ' + c.phone;
                        a.onclick = function(e) {
                            e.preventDefault();
                            clientId.value = c.id;
                            clientLastName.value = c.last_name || '';
                            clientFirstName.value = c.first_name || '';
                            clientPatronymic.value = c.patronymic || '';
                            clientPhone.value = c.phone;
                            clientSearch.value = c.full_name || '';
                            resultsDiv.style.display = 'none';
                        };
                        resultsDiv.appendChild(a);
                    });
                    resultsDiv.style.display = data.length ? 'block' : 'none';
                });
        }, 300);
    });

    // Синхронизация цели визита в скрытое поле и переключение вкладок (выкуп → «Оформление выкупа»)
    var visitPurposeSelect = document.getElementById('visit_purpose');
    var visitPurposeValue = document.getElementById('visit_purpose_value');
    var tabRedemptionWrap = document.getElementById('tab-redemption-wrap');
    var tabClientWrap = document.getElementById('tab-client-wrap');
    var btnVisitNext = document.getElementById('btn-visit-next');
    function syncVisitPurpose() {
        var v = visitPurposeSelect ? visitPurposeSelect.value : 'appraisal';
        if (visitPurposeValue) visitPurposeValue.value = v;
        var isRedemption = (v === 'redemption');
        if (tabRedemptionWrap) tabRedemptionWrap.classList.toggle('d-none', !isRedemption);
        if (tabClientWrap) tabClientWrap.classList.toggle('d-none', isRedemption);
        if (btnVisitNext) {
            btnVisitNext.setAttribute('data-tab-next', isRedemption ? 'pane-redemption' : 'pane-client');
            btnVisitNext.textContent = isRedemption ? 'Далее: Оформление выкупа' : 'Далее: Идентификация клиента';
        }
    }
    if (visitPurposeSelect && visitPurposeValue) {
        visitPurposeSelect.addEventListener('change', syncVisitPurpose);
        document.getElementById('accept-form').addEventListener('submit', syncVisitPurpose);
        syncVisitPurpose();
    }

    // Поиск для оформления выкупа (ФИО, телефон, номер договора)
    var redemptionSearchInput = document.getElementById('redemption_search');
    var redemptionSearchBtn = document.getElementById('redemption_search_btn');
    var redemptionResults = document.getElementById('redemption_search_results');
    var redemptionClientsList = document.getElementById('redemption_clients_list');
    var redemptionEmpty = document.getElementById('redemption_search_empty');
    var redemptionLoading = document.getElementById('redemption_search_loading');
    function doRedemptionSearch() {
        var q = (redemptionSearchInput && redemptionSearchInput.value) ? redemptionSearchInput.value.trim() : '';
        if (q.length < 2) {
            if (redemptionEmpty) { redemptionEmpty.style.display = 'block'; redemptionEmpty.textContent = 'Введите не менее 2 символов.'; }
            return;
        }
        if (redemptionLoading) redemptionLoading.style.display = 'block';
        if (redemptionResults) redemptionResults.style.display = 'none';
        if (redemptionEmpty) redemptionEmpty.style.display = 'none';
        var url = '{{ route("accept.redemption-search") }}?q=' + encodeURIComponent(q);
        fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function(r) {
                if (redemptionLoading) redemptionLoading.style.display = 'none';
                if (!r.ok) {
                    if (redemptionEmpty) {
                        redemptionEmpty.style.display = 'block';
                        r.json().then(function(body) {
                            redemptionEmpty.textContent = body && body.error ? ('Ошибка: ' + body.error) : ('Ошибка ' + r.status + '. Проверьте авторизацию и права доступа.');
                        }).catch(function() {
                            redemptionEmpty.textContent = 'Ошибка ' + r.status + '. Проверьте авторизацию и права доступа.';
                        });
                    }
                    return null;
                }
                return r.json();
            })
            .then(function(data) {
                if (redemptionLoading) redemptionLoading.style.display = 'none';
                if (data === null) return;
                var clients = (data && data.clients) ? data.clients : [];
                if (clients.length === 0) {
                    if (redemptionEmpty) { redemptionEmpty.style.display = 'block'; redemptionEmpty.textContent = 'Ничего не найдено. Уточните запрос (ФИО, телефон или номер договора).'; }
                    return;
                }
                var html = '';
                clients.forEach(function(client) {
                    var contracts = client.contracts || [];
                    if (contracts.length === 0) return;
                    html += '<div class="card mb-3"><div class="card-body"><h6 class="card-title">' + (client.full_name || '—') + (client.phone ? ' <small class="text-muted">' + client.phone + '</small>' : '') + '</h6>';
                    html += '<table class="table table-sm table-bordered mb-0"><thead><tr><th>№ договора</th><th>Товар</th><th>Сумма займа</th><th>Сумма на выкуп</th><th></th></tr></thead><tbody>';
                    contracts.forEach(function(contract) {
                        var redeemUrl = '{{ url("pawn-contracts") }}/' + contract.id + '/redeem?from=accept';
                        html += '<tr><td>' + (contract.contract_number || '—') + '</td><td>' + (contract.item_name || '—') + '</td><td>' + (contract.loan_amount ? Number(contract.loan_amount).toLocaleString('ru-RU') : '—') + ' ₽</td><td>' + (contract.redemption_amount != null ? Number(contract.redemption_amount).toLocaleString('ru-RU') : (contract.buyback_amount != null ? Number(contract.buyback_amount).toLocaleString('ru-RU') : '—')) + ' ₽</td><td><button type="button" class="btn btn-sm btn-success btn-redeem" data-redeem-url="' + redeemUrl.replace(/"/g, '&quot;') + '">Оформить выкуп</button></td></tr>';
                    });
                    html += '</tbody></table></div></div>';
                });
                if (redemptionClientsList) redemptionClientsList.innerHTML = html;
                if (redemptionResults) redemptionResults.style.display = 'block';
            })
            .catch(function(err) {
                if (redemptionLoading) redemptionLoading.style.display = 'none';
                if (redemptionEmpty) { redemptionEmpty.style.display = 'block'; redemptionEmpty.textContent = 'Ошибка запроса. Проверьте сеть или откройте консоль (F12).'; }
                console.error('Redemption search error', err);
            });
    }
    if (redemptionSearchBtn) redemptionSearchBtn.addEventListener('click', doRedemptionSearch);
    if (redemptionSearchInput) {
        redemptionSearchInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); doRedemptionSearch(); } });
    }
    // Отправка выкупа через форму в body (результаты поиска внутри #accept-form — вложенные form не работают)
    var redemptionListEl = document.getElementById('redemption_clients_list');
    if (redemptionListEl) {
        redemptionListEl.addEventListener('click', function(e) {
            var btn = e.target.closest('.btn-redeem');
            if (!btn) return;
            e.preventDefault();
            if (!confirm('Оформить выкуп?')) return;
            var url = btn.getAttribute('data-redeem-url');
            if (!url) return;
            var f = document.createElement('form');
            f.method = 'post';
            f.action = url;
            f.style.display = 'none';
            var tok = document.createElement('input');
            tok.type = 'hidden';
            tok.name = '_token';
            tok.value = '{{ csrf_token() }}';
            f.appendChild(tok);
            document.body.appendChild(f);
            f.submit();
        });
    }

    // Переключение вкладок по кнопкам "Назад" / "Далее"
    document.querySelectorAll('[data-tab-next]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetId = this.getAttribute('data-tab-next');
            var trigger = document.querySelector('[data-bs-target="#' + targetId + '"]');
            if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
        });
    });
    document.querySelectorAll('[data-tab-prev]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetId = this.getAttribute('data-tab-prev');
            var trigger = document.querySelector('[data-bs-target="#' + targetId + '"]');
            if (trigger) bootstrap.Tab.getOrCreateInstance(trigger).show();
        });
    });

    // Фото паспорта: превью и распознавание
    var passportPhotoInput = document.getElementById('passport_photo_input');
    var passportPreviews = document.getElementById('passport_photo_previews');
    var passportParseBtn = document.getElementById('passport_parse_btn');
    var passportParseStatus = document.getElementById('passport_parse_status');
    var clientPassportTextarea = document.getElementById('client_passport');
    var passportFiles = [];

    passportPhotoInput.addEventListener('change', function() {
        passportFiles = Array.from(this.files || []);
        passportPreviews.innerHTML = '';
        passportParseBtn.disabled = passportFiles.length === 0;
        passportParseStatus.textContent = '';
        passportFiles.forEach(function(file, i) {
            var url = URL.createObjectURL(file);
            var div = document.createElement('div');
            div.className = 'position-relative';
            div.innerHTML = '<img src="' + url + '" alt="" style="width:80px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">';
            passportPreviews.appendChild(div);
        });
    });

    passportParseBtn.addEventListener('click', function() {
        if (passportFiles.length === 0) return;
        passportParseStatus.textContent = 'Распознавание…';
        passportParseStatus.className = 'ms-2 small text-muted';
        passportParseBtn.disabled = true;
        var formData = new FormData();
        formData.append('photo', passportFiles[0]);
        formData.append('_token', document.querySelector('input[name="_token"]').value);
        fetch('{{ route("accept.parse-passport") }}', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.passport_data) {
                // Русские буквы (заглавные), цифры, пробелы, знаки препинания и тире
                function onlyRussianUppercase(str) {
                    if (!str) return '';
                    return (str + '').replace(/[^А-Яа-яЁё0-9\s.,:;\-–—]/g, '').replace(/\s+/g, ' ').trim().toUpperCase();
                }
                var raw = (data.passport_data + '').replace(/\n+/g, ' ').replace(/\s+/g, ' ').trim();
                clientPassportTextarea.value = onlyRussianUppercase(raw);
                var f = data.fields || {};
                var ln = document.getElementById('client_last_name');
                var fn = document.getElementById('client_first_name');
                var pn = document.getElementById('client_patronymic');
                if (ln) ln.value = onlyRussianUppercase(f.last_name || '');
                if (fn) fn.value = onlyRussianUppercase(f.first_name || '');
                if (pn) pn.value = onlyRussianUppercase(f.patronymic || '');
                if (document.getElementById('client_birth_date')) document.getElementById('client_birth_date').value = f.birth_date || '';
                if (document.getElementById('client_passport_series_number')) document.getElementById('client_passport_series_number').value = f.passport_series_number || '';
                if (document.getElementById('client_passport_issued_by')) document.getElementById('client_passport_issued_by').value = onlyRussianUppercase(f.issued_by || '');
                if (document.getElementById('client_passport_issued_at')) document.getElementById('client_passport_issued_at').value = f.issued_at || '';
                var by = data.parsed_by === 'deepseek' ? ' (Deep Seek)' : data.parsed_by === 'openai' ? ' (OpenAI)' : ' (шаблон)';
                var hint = '';
                if (data.parsed_by !== 'openai' && data.llm_error) {
                    hint = ' ' + data.llm_error;
                } else if (data.parsed_by !== 'openai' && data.parsed_by !== 'deepseek') {
                    hint = '. Для AI: задайте DEEPSEEK_API_KEY или OPENAI_API_KEY в .env, затем php artisan config:clear';
                }
                passportParseStatus.textContent = 'Данные заполнены по фото.' + by + hint;
                passportParseStatus.className = 'ms-2 small text-success';
            } else {
                passportParseStatus.textContent = data.error || 'Не удалось распознать текст.';
                passportParseStatus.className = 'ms-2 small text-danger';
            }
        })
        .catch(function() {
            passportParseStatus.textContent = 'Ошибка запроса.';
            passportParseStatus.className = 'ms-2 small text-danger';
        })
        .finally(function() { passportParseBtn.disabled = passportFiles.length === 0; });
    });
})();
</script>
@endpush
@endsection
