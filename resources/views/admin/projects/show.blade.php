@extends('layouts.admin')
@section('title', $project->name)
@section('content')
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="h4 mb-1">{{ $project->name }}</h1>
        @if($project->description)
            <p class="text-muted small mb-0">{{ Str::limit($project->description, 200) }}</p>
        @endif
    </div>
    <div>
        <a href="{{ route('admin.projects.edit', $project) }}" class="btn btn-outline-primary">Изменить</a>
        <form method="post" action="{{ route('admin.projects.destroy', $project) }}" class="d-inline" onsubmit="return confirm('Удалить проект и все статьи расхода?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Удалить</button>
        </form>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="adminProjectTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="admin-expenses-tab" data-bs-toggle="tab" data-bs-target="#admin-expenses" type="button" role="tab">Расходы и операции</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="admin-investments-tab" data-bs-toggle="tab" data-bs-target="#admin-investments" type="button" role="tab"><i class="bi bi-wallet2 me-1"></i> Инвестиции клиентов</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="admin-stages-tab" data-bs-toggle="tab" data-bs-target="#admin-stages" type="button" role="tab"><i class="bi bi-tools me-1"></i> Этапы строительства</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="admin-apartments-tab" data-bs-toggle="tab" data-bs-target="#admin-apartments" type="button" role="tab">Квартиры</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="admin-site-tab" data-bs-toggle="tab" data-bs-target="#admin-site" type="button" role="tab">Размещение на сайте</button>
    </li>
</ul>

<div class="tab-content" id="adminProjectTabsContent">
<div class="tab-pane fade show active" id="admin-expenses" role="tabpanel">

{{-- Сводная таблица по расходам --}}
<h5 class="mb-3">Сводка по расходам</h5>
<div class="card mb-4">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Статья расхода</th>
                    <th class="text-end">Кол-во операций</th>
                    <th class="text-end">Сумма, {{ \App\Models\Setting::get('currency', 'RUB') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($summaryByItem as $row)
                <tr>
                    <td>{{ $row['item']->name }}</td>
                    <td class="text-end">{{ $row['count'] }}</td>
                    <td class="text-end">{{ number_format($row['total'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-muted">Нет расходов по статьям. Добавьте статьи расхода и создавайте операции «Расход на проект» у клиентов.</td></tr>
                @endforelse
            </tbody>
            @if($summaryByItem->isNotEmpty())
            <tfoot class="table-light">
                <tr>
                    <th>Итого</th>
                    <th class="text-end">{{ $project->balanceTransactions->count() }}</th>
                    <th class="text-end">{{ number_format($grandTotal, 2) }}</th>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- Список статей расхода + форма добавления --}}
<h5 class="mb-3">Статьи расхода на проект</h5>
<div class="card mb-4">
    <div class="card-body">
        <form method="post" action="{{ route('admin.projects.expense-items.store', $project) }}" class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-auto flex-grow-1">
                <label class="form-label small mb-0">Новая статья</label>
                <input type="text" name="name" class="form-control form-control-sm" placeholder="Название статьи расхода" required>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">Добавить</button>
            </div>
        </form>
        <ul class="list-group list-group-flush">
            @forelse($project->expenseItems as $item)
            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                <span>{{ $item->name }}</span>
                <form method="post" action="{{ route('admin.projects.expense-items.destroy', [$project, $item]) }}" class="d-inline" onsubmit="return confirm('Удалить статью расхода?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                </form>
            </li>
            @empty
            <li class="list-group-item text-muted">Нет статей. Добавьте статью расхода выше.</li>
            @endforelse
        </ul>
    </div>
</div>

{{-- Последние транзакции по проекту --}}
<h5 class="mb-3">Операции по проекту</h5>
<div class="card">
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Дата</th>
                    <th>Клиент</th>
                    <th>Статья расхода</th>
                    <th class="text-end">Сумма</th>
                    <th>Комментарий</th>
                </tr>
            </thead>
            <tbody>
                @forelse($project->balanceTransactions as $t)
                <tr>
                    <td>{{ $t->created_at->format('d.m.Y H:i') }}</td>
                    <td><a href="{{ route('admin.clients.show', $t->client) }}">{{ $t->client->full_name }}</a></td>
                    <td>{{ $t->projectExpenseItem?->name ?? '—' }}</td>
                    <td class="text-end">{{ number_format($t->amount, 2) }}</td>
                    <td>{{ Str::limit($t->comment, 40) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-muted">Нет операций. Создайте операцию «Расход на проект» в карточке клиента.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

</div>{{-- /tab-pane expenses --}}

{{-- Вкладка: Инвестиции клиентов --}}
<div class="tab-pane fade" id="admin-investments" role="tabpanel">
    <p class="text-muted small mb-3">Инвестиции клиентов — данные из вкладки «Мои инвестиции» в личном кабинете. Хранятся отдельно от операций по балансу.</p>
    @forelse($investmentsByClient ?? [] as $row)
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><a href="{{ route('admin.clients.show', $row['client']) }}">{{ $row['client']->full_name }}</a></strong>
            <span class="badge bg-primary">{{ number_format($row['total'], 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</span>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Статья расхода</th>
                        <th class="text-end">Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($row['byArticle'] as $article => $sum)
                    <tr>
                        <td>{{ e($article) }}</td>
                        <td class="text-end">{{ number_format($sum, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th>Итого по клиенту</th>
                        <th class="text-end">{{ number_format($row['total'], 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @empty
    <div class="card">
        <div class="card-body text-muted text-center py-5">Нет инвестиций. Клиенты добавляют их во вкладке «Мои инвестиции» в личном кабинете.</div>
    </div>
    @endforelse
    @if(isset($investmentsByClient) && $investmentsByClient->isNotEmpty())
    <div class="card border-primary">
        <div class="card-body d-flex justify-content-between align-items-center">
            <strong>Общая сумма инвестиций</strong>
            <h5 class="mb-0">{{ number_format($investmentsGrandTotal ?? 0, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</h5>
        </div>
    </div>
    @endif
</div>{{-- /tab-pane investments --}}

{{-- Вкладка: Этапы строительства --}}
<div class="tab-pane fade" id="admin-stages" role="tabpanel">
    <h5 class="mb-3">Справочник этапов строительства</h5>
    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#admin-stages-table" role="tab">Таблица</button>
        </li>
        <li class="nav-item">
            <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#admin-stages-gantt" role="tab">Диаграмма Ганта</button>
        </li>
    </ul>
    <div class="tab-content">
    <div class="tab-pane fade show active" id="admin-stages-table" role="tabpanel">
    <div class="card mb-4">
        <div class="card-body">
            <form method="post" action="{{ route('admin.projects.construction-stages.store', $project) }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-2">
                    <label class="form-label small mb-0">Название этапа *</label>
                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Фундамент" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Ответственный</label>
                    <select name="client_id" class="form-select form-select-sm">
                        <option value="">— не выбран —</option>
                        @foreach(\App\Models\Client::orderBy('first_name')->orderBy('last_name')->get(['id', 'first_name', 'last_name']) as $c)
                            <option value="{{ $c->id }}">{{ $c->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-0">Статус</label>
                    <select name="status" class="form-select form-select-sm">
                        @foreach(\App\Models\ConstructionStage::statusLabels() as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small mb-0">Бюджет</label>
                    <input type="number" name="budget" step="0.01" min="0" class="form-control form-control-sm" placeholder="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Подрядчик</label>
                    <input type="text" name="contractor" class="form-control form-control-sm" placeholder="Название">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">План: начало / дата окончания этапа</label>
                    <div class="input-group input-group-sm">
                        <input type="date" name="planned_start_date" class="form-control form-control-sm" placeholder="Начало" title="План начала">
                        <input type="date" name="planned_end_date" class="form-control form-control-sm" placeholder="Окончание" title="Дата окончания этапа (план)">
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm">Добавить</button>
                </div>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Название этапа</th>
                        <th>Ответственный</th>
                        <th>Статус</th>
                        <th class="text-end">Бюджет</th>
                        <th>Подрядчик</th>
                        <th>Даты (план / факт)</th>
                        <th style="width: 140px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($project->constructionStages as $stage)
                    <tr>
                        <td>{{ e($stage->name) }}</td>
                        <td>{{ $stage->client ? $stage->client->full_name : '—' }}</td>
                        <td><span class="badge bg-{{ $stage->status === 'completed' ? 'success' : ($stage->status === 'in_progress' ? 'primary' : 'secondary') }}">{{ $stage->status_label }}</span></td>
                        <td class="text-end">{{ $stage->budget !== null ? number_format($stage->budget, 2) . ' ' . \App\Models\Setting::get('currency', 'RUB') : '—' }}</td>
                        <td>{{ $stage->contractor ? e($stage->contractor) : '—' }}</td>
                        <td class="small">
                            @if($stage->planned_start_date || $stage->planned_end_date)
                                План: {{ $stage->planned_start_date?->format('d.m.Y') ?? '—' }} – {{ $stage->planned_end_date?->format('d.m.Y') ?? '—' }}
                            @else — @endif
                            @if($stage->actual_start_date || $stage->actual_end_date)
                                <br>Факт: {{ $stage->actual_start_date?->format('d.m.Y') ?? '—' }} – {{ $stage->actual_end_date?->format('d.m.Y') ?? '—' }}
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.projects.construction-stages.edit', [$project, $stage]) }}" class="btn btn-sm btn-outline-primary me-1">Изменить</a>
                            <form method="post" action="{{ route('admin.projects.construction-stages.destroy', [$project, $stage]) }}" class="d-inline" onsubmit="return confirm('Удалить этап?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted text-center py-4">Нет этапов. Добавьте этап выше.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>{{-- /tab-pane table --}}
    <div class="tab-pane fade" id="admin-stages-gantt" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <p class="text-muted small mb-2">Нажмите <i class="bi bi-chevron-right"></i> у этапа, чтобы раскрыть виды работ.</p>
                @include('partials.gantt-stages', ['stages' => $project->constructionStages])
            </div>
        </div>
    </div>
    </div>{{-- /tab-content --}}
</div>{{-- /tab-pane stages --}}

{{-- Вкладка Квартиры --}}
<div class="tab-pane fade" id="admin-apartments" role="tabpanel">
    @php
        $apts = $project->apartments;
        $countSold = $apts->where('status', 'sold')->count();
        $countAvailable = $apts->where('status', 'available')->count();
        $countInPledge = $apts->where('status', 'in_pledge')->count();
        $areaSold = $apts->where('status', 'sold')->sum(function ($a) { return (float) ($a->living_area ?? 0); });
        $areaTotal = $apts->sum(function ($a) { return (float) ($a->living_area ?? 0); });
        $areaLeft = $areaTotal - $areaSold;
    @endphp
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title mb-3">Сводка по квартирам</h6>
            <div class="row g-3">
                <div class="col-6 col-md-4">
                    <div class="text-muted small">Продано</div>
                    <strong>{{ $countSold }} кв.</strong>
                </div>
                <div class="col-6 col-md-4">
                    <div class="text-muted small">Свободно</div>
                    <strong>{{ $countAvailable }} кв.</strong>
                </div>
                <div class="col-6 col-md-4">
                    <div class="text-muted small">В залоге</div>
                    <strong>{{ $countInPledge }} кв.</strong>
                </div>
                <div class="col-6 col-md-4">
                    <div class="text-muted small">м² продано</div>
                    <strong>{{ number_format($areaSold, 1) }} м²</strong>
                </div>
                <div class="col-6 col-md-4">
                    <div class="text-muted small">м² осталось</div>
                    <strong>{{ number_format($areaLeft, 1) }} м²</strong>
                </div>
                <div class="col-6 col-md-4">
                    <div class="text-muted small">Всего м²</div>
                    <strong>{{ number_format($areaTotal, 1) }} м²</strong>
                </div>
            </div>
        </div>
    </div>
    <div class="mb-3">
        <a href="{{ route('admin.projects.apartments.create', $project) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Создать карточку квартиры</a>
    </div>
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle" id="admin-apartments-table">
                <thead class="table-light">
                    <tr>
                        <th class="apartment-sort" data-sort="number" style="cursor:pointer" title="Сортировать">№ квартиры <i class="bi bi-arrow-down-up small"></i></th>
                        <th class="apartment-sort" data-sort="floor" style="cursor:pointer" title="Сортировать">Этаж <i class="bi bi-arrow-down-up small"></i></th>
                        <th class="apartment-sort" data-sort="area" style="cursor:pointer" title="Сортировать">Площадь, м² <i class="bi bi-arrow-down-up small"></i></th>
                        <th class="apartment-sort" data-sort="rooms" style="cursor:pointer" title="Сортировать">Комнат <i class="bi bi-arrow-down-up small"></i></th>
                        <th class="apartment-sort" data-sort="status" style="cursor:pointer" title="Сортировать">Статус <i class="bi bi-arrow-down-up small"></i></th>
                        <th>Владелец</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($project->apartments as $apt)
                    <tr data-number="{{ $apt->apartment_number }}" data-number-int="{{ is_numeric($apt->apartment_number) ? (int)$apt->apartment_number : 999999 }}" data-floor="{{ $apt->floor ?? '' }}" data-area="{{ $apt->living_area ?? '' }}" data-rooms="{{ $apt->rooms_count ?? '' }}" data-status="{{ $apt->status }}">
                        <td><a href="{{ route('admin.projects.apartments.show', [$project, $apt]) }}">{{ $apt->apartment_number }}</a></td>
                        <td>{{ $apt->floor ?? '—' }}</td>
                        <td>{{ $apt->living_area ?? '—' }}</td>
                        <td>{{ $apt->rooms_count ?? '—' }}</td>
                        <td><span class="badge bg-{{ $apt->status === 'sold' ? 'secondary' : ($apt->status === 'in_pledge' ? 'warning text-dark' : 'success') }}">{{ $apt->status_label }}</span></td>
                        <td class="small text-muted">{{ $apt->owner_data ? Str::limit(strip_tags($apt->owner_data), 40) : '—' }}</td>
                        <td>
                            <a href="{{ route('admin.projects.apartments.show', [$project, $apt]) }}" class="btn btn-sm btn-outline-primary">Карточка</a>
                            <a href="{{ route('admin.projects.apartments.edit', [$project, $apt]) }}" class="btn btn-sm btn-outline-secondary">Изменить</a>
                            <form method="post" action="{{ route('admin.projects.apartments.destroy', [$project, $apt]) }}" class="d-inline" onsubmit="return confirm('Удалить карточку квартиры?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted">Нет квартир. Создайте карточку квартиры.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <script>
    (function() {
        var table = document.getElementById('admin-apartments-table');
        if (!table) return;
        var tbody = table.querySelector('tbody');
        var headerCells = table.querySelectorAll('.apartment-sort');
        var statusOrder = { available: 1, in_pledge: 2, sold: 3 };
        function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
        function getSortKey(tr, key) {
            if (key === 'number') return tr.getAttribute('data-number-int') !== null ? parseInt(tr.getAttribute('data-number-int'), 10) : (num(tr.getAttribute('data-number')) || 0);
            if (key === 'floor') return num(tr.getAttribute('data-floor'));
            if (key === 'area') return num(tr.getAttribute('data-area'));
            if (key === 'rooms') return num(tr.getAttribute('data-rooms'));
            if (key === 'status') return statusOrder[tr.getAttribute('data-status')] || 0;
            return 0;
        }
        function sortTable(key, dir) {
            var rows = [].slice.call(tbody.querySelectorAll('tr[data-number]'));
            if (!rows.length) return;
            rows.sort(function(a, b) {
                var va = getSortKey(a, key), vb = getSortKey(b, key);
                if (key === 'number' && va === vb) {
                    var na = (a.getAttribute('data-number') || '').toString(), nb = (b.getAttribute('data-number') || '').toString();
                    return dir * (na.localeCompare(nb, undefined, { numeric: true }) || 0);
                }
                if (va < vb) return -dir;
                if (va > vb) return dir;
                return 0;
            });
            rows.forEach(function(r) { tbody.appendChild(r); });
        }
        var currentSort = { key: 'number', dir: 1 };
        headerCells.forEach(function(th) {
            th.addEventListener('click', function() {
                var key = this.getAttribute('data-sort');
                if (!key) return;
                currentSort.dir = (currentSort.key === key ? -currentSort.dir : 1);
                currentSort.key = key;
                sortTable(key, currentSort.dir);
            });
        });
    })();
    </script>
</div>{{-- /tab-pane apartments --}}

{{-- Вкладка: Размещение на сайте --}}
<div class="tab-pane fade" id="admin-site" role="tabpanel">
    <p class="text-muted small mb-4">Заполните данные — объект появится в блоке «Строящиеся объекты» на главной странице сайта.</p>
    <form method="post" action="{{ route('admin.projects.site-settings', $project) }}" class="card mb-4">
        @csrf
        <div class="card-body">
            <h6 class="card-title mb-3">Настройки отображения</h6>
            <div class="mb-3">
                <div class="form-check">
                    <input type="hidden" name="show_on_site" value="0">
                    <input type="checkbox" name="show_on_site" value="1" class="form-check-input" id="show_on_site" @checked($project->show_on_site)>
                    <label class="form-check-label" for="show_on_site">Показывать на главной странице</label>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Описание для сайта</label>
                <textarea name="site_description" class="form-control @error('site_description') is-invalid @enderror" rows="4" placeholder="Краткое описание объекта для посетителей сайта">{{ old('site_description', $project->site_description) }}</textarea>
                @error('site_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Карта Яндекса (адрес или ссылка iframe)</label>
                <input type="text" name="map_embed_url" class="form-control @error('map_embed_url') is-invalid @enderror" value="{{ old('map_embed_url', $project->map_embed_url) }}" placeholder="https://yandex.ru/map-widget/v1/?ll=82.9342%2C55.0302&z=15&l=map или адрес">
                <small class="form-text text-muted">Вставьте полную ссылку iframe из конструктора карт Яндекса или адрес (например: Новосибирск, ул. Ленина, 1)</small>
                @error('map_embed_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Сохранить настройки</button>
        </div>
    </form>

    <h6 class="mb-2">Фотографии для слайдера</h6>
    <p class="text-muted small mb-3">Загруженные фото отображаются в карусели на карточке объекта на главной.</p>
    <form method="post" action="{{ route('admin.projects.site-photos.store', $project) }}" enctype="multipart/form-data" class="card mb-4">
        @csrf
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small mb-0">Добавить фото</label>
                    <input type="file" name="photo" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp" required>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Загрузить</button>
                </div>
            </div>
        </div>
    </form>
    <div class="card">
        <div class="card-body">
            @forelse($project->sitePhotos as $photo)
            <div class="d-inline-block me-2 mb-2 position-relative">
                <img src="{{ $photo->url }}" alt="" class="rounded" style="height: 100px; width: auto; object-fit: cover;">
                <form method="post" action="{{ route('admin.projects.site-photos.destroy', [$project, $photo]) }}" class="position-absolute top-0 end-0 m-1 d-inline" onsubmit="return confirm('Удалить фото?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger rounded-circle p-0" style="width:24px;height:24px;line-height:22px;">×</button>
                </form>
            </div>
            @empty
            <p class="text-muted mb-0">Нет загруженных фото. Добавьте фото выше.</p>
            @endforelse
        </div>
    </div>
</div>{{-- /tab-pane site --}}

</div>{{-- /tab-content --}}

<p class="mt-3 mb-0"><a href="{{ route('admin.projects.index') }}" class="btn btn-secondary">← К списку проектов</a></p>
@endsection
