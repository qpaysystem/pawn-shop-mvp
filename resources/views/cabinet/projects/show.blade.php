@extends('cabinet.layout')
@section('title', $project->name)
@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div>
        <a href="{{ route('cabinet.projects.index') }}" class="btn btn-sm btn-outline-secondary mb-2">← К списку проектов</a>
        <h1 class="h4 mb-1">{{ $project->name }}</h1>
        @if($project->description)
            <p class="text-muted small mb-0">{{ Str::limit($project->description, 300) }}</p>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<ul class="nav nav-tabs mb-3" id="projectTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ session('active_tab') !== 'investments' ? 'active' : '' }}" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab"><i class="bi bi-pie-chart me-1"></i> Сводная информация</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="expenses-tab" data-bs-toggle="tab" data-bs-target="#expenses" type="button" role="tab">Расходы на проект</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ session('active_tab') === 'investments' ? 'active' : '' }}" id="investments-tab" data-bs-toggle="tab" data-bs-target="#investments" type="button" role="tab"><i class="bi bi-wallet2 me-1"></i> Мои инвестиции</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="stages-tab" data-bs-toggle="tab" data-bs-target="#stages" type="button" role="tab"><i class="bi bi-tools me-1"></i> Этапы строительства</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="apartments-tab" data-bs-toggle="tab" data-bs-target="#apartments" type="button" role="tab">Квартиры</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="docs-tab" data-bs-toggle="tab" data-bs-target="#docs" type="button" role="tab">Рабочая документация</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button" role="tab">Задачи</button>
    </li>
</ul>

<div class="tab-content" id="projectTabsContent">
    {{-- Вкладка: Сводная информация --}}
    @php
        $apts = $project->apartments;
        $countSold = $apts->where('status', 'sold')->count();
        $countAvailable = $apts->where('status', 'available')->count();
        $countInPledge = $apts->where('status', 'in_pledge')->count();
        $areaSold = $apts->where('status', 'sold')->sum(function ($a) { return (float) ($a->living_area ?? 0); });
        $areaTotal = $apts->sum(function ($a) { return (float) ($a->living_area ?? 0); });
        $areaLeft = $areaTotal - $areaSold;
    @endphp
    @php $defaultActive = session('active_tab') !== 'investments'; @endphp
    <div class="tab-pane fade {{ $defaultActive ? 'show active' : '' }}" id="summary" role="tabpanel">
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted small mb-1">Квартиры</h6>
                        <div class="d-flex flex-wrap gap-2 small">
                            <span>Продано: <strong>{{ $countSold }}</strong></span>
                            <span>Свободно: <strong>{{ $countAvailable }}</strong></span>
                            <span>В залоге: <strong>{{ $countInPledge }}</strong></span>
                        </div>
                        <div class="mt-1 small text-muted">м²: {{ number_format($areaTotal, 1) }} всего, {{ number_format($areaSold, 1) }} продано</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted small mb-1">Инвестиции (общие)</h6>
                        <h4 class="mb-0">{{ number_format($investmentsGrandTotal ?? 0, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</h4>
                        <div class="mt-1 small text-muted">Сумма по всем клиентам</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted small mb-1">Сумма расходов</h6>
                        <h4 class="mb-0">{{ number_format($grandTotal, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</h4>
                        <div class="mt-1 small text-muted">{{ $project->balanceTransactions->count() }} операций</div>
                    </div>
                </div>
            </div>
        </div>

        <h5 class="mb-2">Расходы по статьям</h5>
        <div class="card border-0 shadow-sm mb-4">
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
                        <tr><td colspan="3" class="text-muted">Нет расходов по статьям</td></tr>
                        @endforelse
                    </tbody>
                    @if($summaryByItem->isNotEmpty())
                    <tfoot class="table-light">
                        <tr>
                            <th>Итого</th>
                            <th class="text-end">{{ $project->balanceTransactions->count() }}</th>
                            <th class="text-end">{{ number_format($grandTotal, 2) }}</th>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <h5 class="mb-2">Инвестиции клиентов</h5>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                @forelse($investmentsByClient ?? [] as $row)
                <div class="border-bottom">
                    <div class="d-flex justify-content-between align-items-center px-3 py-2" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#inv-client-{{ $row['client']->id }}" aria-expanded="false">
                        <span>{{ $row['client']->full_name }}</span>
                        <span class="badge bg-primary">{{ number_format($row['total'], 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</span>
                    </div>
                    <div class="collapse" id="inv-client-{{ $row['client']->id }}">
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                @foreach($row['byArticle'] as $article => $sum)
                                <tr>
                                    <td class="ps-4 text-muted small">{{ e($article) }}</td>
                                    <td class="text-end">{{ number_format($sum, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @empty
                <div class="px-3 py-4 text-muted text-center">Нет инвестиций</div>
                @endforelse
                @if(isset($investmentsByClient) && $investmentsByClient->isNotEmpty())
                <div class="table-light px-3 py-2 fw-bold d-flex justify-content-between">
                    <span>Итого инвестиций</span>
                    <span>{{ number_format($investmentsGrandTotal ?? 0, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</span>
                </div>
                @endif
            </div>
        </div>

        <h5 class="mb-2">Квартиры по клиентам</h5>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Клиент (ответственный)</th>
                            <th class="text-end">Кол-во квартир</th>
                            <th class="text-end">м²</th>
                            <th class="text-end">Сумма проданных</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($apartmentsByClient ?? [] as $row)
                        <tr>
                            <td>{{ $row['client']->full_name }}</td>
                            <td class="text-end">{{ $row['count'] }}</td>
                            <td class="text-end">{{ number_format($row['area'], 1) }}</td>
                            <td class="text-end">{{ number_format($row['soldSum'], 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-muted">Нет квартир с указанным ответственным</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <h5 class="mb-2">Займы на текущую дату</h5>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Клиент</th>
                            <th class="text-end">Сумма займа (остаток)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loansByClient ?? [] as $row)
                        <tr>
                            <td>{{ $row['client']->full_name }}</td>
                            <td class="text-end">{{ number_format($row['amount'], 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-muted">Нет данных о займах по клиентам проекта</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <h5 class="mb-2">Расходы по клиентам в проект</h5>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Клиент</th>
                            <th class="text-end">Операций</th>
                            <th class="text-end">Сумма расходов</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summaryByClient as $row)
                        <tr>
                            <td>{{ $row['client']->full_name }}</td>
                            <td class="text-end">{{ $row['count'] }}</td>
                            <td class="text-end">{{ number_format($row['total'], 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-muted">Нет данных по клиентам</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Вкладка: Мои инвестиции --}}
    <div class="tab-pane fade {{ session('active_tab') === 'investments' ? 'show active' : '' }}" id="investments" role="tabpanel">
        <div class="mb-4">
            <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#add-investment-form" aria-expanded="false"><i class="bi bi-plus-lg me-1"></i> Добавить расход</button>
            <div class="collapse mt-3" id="add-investment-form">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Новый расход</h6>
                        <form method="post" action="{{ route('cabinet.projects.investments.store', $project) }}">
                            @csrf
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label">Статья расхода *</label>
                                    <input type="text" name="expense_item_name" list="expense-item-list" class="form-control @error('expense_item_name') is-invalid @enderror" value="{{ old('expense_item_name') }}" placeholder="Выберите или введите новую статью" required>
                                    <datalist id="expense-item-list">
                                        @foreach($expenseItemSuggestions ?? [] as $s)
                                            <option value="{{ e($s) }}">
                                        @endforeach
                                    </datalist>
                                    @error('expense_item_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <small class="text-muted">Можно выбрать из списка или ввести свой вид расхода</small>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Сумма, {{ \App\Models\Setting::get('currency', 'RUB') }} *</label>
                                    <input type="number" name="amount" step="0.01" min="0" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Комментарий</label>
                                    <input type="text" name="comment" class="form-control @error('comment') is-invalid @enderror" value="{{ old('comment') }}" placeholder="Необязательно">
                                    @error('comment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">Добавить</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Статья расхода</th>
                            <th class="text-end">Сумма</th>
                            <th>Комментарий</th>
                            <th class="text-end" style="width: 80px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($myInvestments ?? [] as $inv)
                        <tr>
                            <td>{{ e($inv->expense_item_name) }}</td>
                            <td class="text-end">{{ number_format($inv->amount, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</td>
                            <td class="text-muted small">{{ $inv->comment ? Str::limit(e($inv->comment), 80) : '—' }}</td>
                            <td class="text-end">
                                <form method="post" action="{{ route('cabinet.projects.investments.destroy', [$project, $inv]) }}" class="d-inline" onsubmit="return confirm('Удалить расход?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0">Удалить</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-muted text-center py-4">Нет расходов. Нажмите «Добавить расход».</td></tr>
                        @endforelse
                    </tbody>
                    @if(isset($myInvestments) && $myInvestments->isNotEmpty())
                    <tfoot class="table-light">
                        <tr>
                            <th>Итого расходов</th>
                            <th class="text-end">{{ number_format($myInvestmentsTotal ?? 0, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    {{-- Вкладка: Расходы на проект --}}
    <div class="tab-pane fade" id="expenses" role="tabpanel">
        <h5 class="mb-3">Расходы по статьям</h5>
        <div class="card border-0 shadow-sm mb-4">
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
                        <tr><td colspan="3" class="text-muted">Нет расходов по статьям</td></tr>
                        @endforelse
                    </tbody>
                    @if($summaryByItem->isNotEmpty())
                    <tfoot class="table-light">
                        <tr>
                            <th>Итого</th>
                            <th class="text-end">{{ $project->balanceTransactions->count() }}</th>
                            <th class="text-end">{{ number_format($grandTotal, 2) }}</th>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>

        <h5 class="mb-3">Сводка по клиентам</h5>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Клиент</th>
                            <th class="text-end">Операций</th>
                            <th class="text-end">Сумма расходов</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summaryByClient as $row)
                        <tr>
                            <td>{{ $row['client']->full_name }}</td>
                            <td class="text-end">{{ $row['count'] }}</td>
                            <td class="text-end">{{ number_format($row['total'], 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-muted">Нет данных по клиентам</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <h5 class="mb-3">Все транзакции по проекту</h5>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
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
                                <td>{{ $t->client->full_name }}</td>
                                <td>{{ $t->projectExpenseItem?->name ?? '—' }}</td>
                                <td class="text-end">{{ number_format($t->amount, 2) }}</td>
                                <td class="text-muted small">{{ Str::limit($t->comment, 50) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-muted">Нет операций по проекту</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Вкладка: Этапы строительства --}}
    @php
        $stages = $project->constructionStages ?? collect();
        $stagesCompleted = $stages->where('status', \App\Models\ConstructionStage::STATUS_COMPLETED);
        $stagesTotal = $stages->count();
        $stagesProgressPercent = $stagesTotal > 0 ? round($stagesCompleted->count() / $stagesTotal * 100) : 0;
        $stagesOverdue = $stages->filter(fn($s) => $s->is_overdue);
        $now = now();
        $deadlineEnd = $now->copy()->addDays(14);
        $stagesNearestDeadlines = $stages->filter(function ($s) use ($now, $deadlineEnd) {
            if ($s->status === \App\Models\ConstructionStage::STATUS_COMPLETED || !$s->planned_end_date) return false;
            return $s->planned_end_date->between($now, $deadlineEnd);
        })->sortBy('planned_end_date')->take(5);
        $stagesByStatus = [
            \App\Models\ConstructionStage::STATUS_NOT_STARTED => $stages->where('status', \App\Models\ConstructionStage::STATUS_NOT_STARTED)->values(),
            \App\Models\ConstructionStage::STATUS_IN_PROGRESS => $stages->where('status', \App\Models\ConstructionStage::STATUS_IN_PROGRESS)->values(),
            \App\Models\ConstructionStage::STATUS_COMPLETED => $stages->where('status', \App\Models\ConstructionStage::STATUS_COMPLETED)->values(),
        ];
    @endphp
    <div class="tab-pane fade" id="stages" role="tabpanel">
        <h5 class="mb-3">Этапы строительства</h5>

        <ul class="nav nav-pills mb-3">
            <li class="nav-item">
                <button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#stages-kanban-view" role="tab">Канбан</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#stages-gantt-view" role="tab">Диаграмма Ганта</button>
            </li>
        </ul>
        <div class="tab-content">
        <div class="tab-pane fade show active" id="stages-kanban-view" role="tabpanel">

        {{-- Сводный дашборд --}}
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted small mb-1">Выполнение</h6>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height: 1.5rem;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: {{ $stagesProgressPercent }}%;" aria-valuenow="{{ $stagesProgressPercent }}" aria-valuemin="0" aria-valuemax="100">{{ $stagesProgressPercent }}%</div>
                            </div>
                        </div>
                        <small class="text-muted">Завершено {{ $stagesCompleted->count() }} из {{ $stagesTotal }} этапов</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted small mb-1">Отстающие этапы</h6>
                        @if($stagesOverdue->isEmpty())
                            <p class="mb-0 text-success small">Нет отстающих</p>
                        @else
                            <ul class="list-unstyled mb-0 small">
                                @foreach($stagesOverdue->take(3) as $s)
                                <li><a href="#" class="stage-card-link text-danger" data-stage-id="{{ $s->id }}">{{ e($s->name) }}</a></li>
                                @endforeach
                                @if($stagesOverdue->count() > 3)
                                <li class="text-muted">и ещё {{ $stagesOverdue->count() - 3 }}</li>
                                @endif
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted small mb-1">Ближайшие дедлайны</h6>
                        @if($stagesNearestDeadlines->isEmpty())
                            <p class="mb-0 text-muted small">Нет в ближайшие 14 дней</p>
                        @else
                            <ul class="list-unstyled mb-0 small">
                                @foreach($stagesNearestDeadlines as $s)
                                <li><a href="#" class="stage-card-link" data-stage-id="{{ $s->id }}">{{ e($s->name) }}</a> — {{ $s->planned_end_date->format('d.m.Y') }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <p class="text-muted small mb-2">Клик по карточке этапа открывает детальную карточку с фотоотчётами и обсуждениями. Этапы добавляются в админке.</p>

        {{-- Канбан этапов --}}
        <div class="row g-3 mb-3 stages-kanban" data-project-id="{{ $project->id }}" data-base-url="{{ route('cabinet.projects.show', $project) }}" data-csrf="{{ csrf_token() }}">
            @foreach(\App\Models\ConstructionStage::statusesForBoard() as $status)
            @php $label = \App\Models\ConstructionStage::statusLabels()[$status]; $items = $stagesByStatus[$status] ?? []; @endphp
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 stages-kanban-col stages-pastel-{{ $status }}">
                    <div class="card-header py-2">
                        <strong>{{ $label }}</strong>
                        <span class="badge bg-secondary ms-1">{{ count($items) }}</span>
                    </div>
                    <div class="card-body p-2" style="min-height: 200px;">
                        @foreach($items as $stage)
                        <div class="card mb-2 stage-card bg-white border" data-stage-id="{{ $stage->id }}" data-stage-name="{{ e($stage->name) }}" style="cursor: pointer;">
                            <div class="card-body py-2 px-3">
                                <div class="fw-semibold">{{ e($stage->name) }}</div>
                                @if($stage->client)
                                    <div class="small text-muted mt-1"><i class="bi bi-person me-1"></i>{{ $stage->client->full_name }}</div>
                                @endif
                                @if($stage->planned_end_date)
                                    <div class="small text-muted">Дедлайн: {{ $stage->planned_end_date->format('d.m.Y') }}</div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                        @if(empty($items))
                            <p class="text-muted small mb-0">Нет этапов</p>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Модалка этапа --}}
        <div class="modal fade" id="stageDetailModal" tabindex="-1" aria-labelledby="stageDetailModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="stageDetailModalLabel">Этап</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body" id="stageDetailModalBody">
                        <div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Загрузка...</div>
                    </div>
                </div>
            </div>
        </div>
        </div>{{-- /tab-pane kanban --}}
        <div class="tab-pane fade" id="stages-gantt-view" role="tabpanel">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <p class="text-muted small mb-2">Нажмите <i class="bi bi-chevron-right"></i> у этапа, чтобы раскрыть виды работ.</p>
                    @include('partials.gantt-stages', ['stages' => $stages])
                </div>
            </div>
        </div>
        </div>{{-- /tab-content --}}
    </div>

    {{-- Вкладка: Квартиры --}}
    <div class="tab-pane fade" id="apartments" role="tabpanel">
        @php
            $apts = $project->apartments;
            $countSold = $apts->where('status', 'sold')->count();
            $countAvailable = $apts->where('status', 'available')->count();
            $countInPledge = $apts->where('status', 'in_pledge')->count();
            $areaSold = $apts->where('status', 'sold')->sum(function ($a) { return (float) ($a->living_area ?? 0); });
            $areaTotal = $apts->sum(function ($a) { return (float) ($a->living_area ?? 0); });
            $areaLeft = $areaTotal - $areaSold;
        @endphp
        <div class="card border-0 shadow-sm mb-4">
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
        <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3 mb-3">
            <a href="{{ route('cabinet.projects.apartments.create', $project) }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Создать карточку квартиры</a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <label class="form-label small mb-0 text-nowrap">Статус:</label>
                <select id="apartment-filter" class="form-select form-select-sm" style="width: auto;">
                    <option value="">Все</option>
                    <option value="available">Свободно</option>
                    <option value="in_pledge">В залоге</option>
                    <option value="sold">Продано</option>
                </select>
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check" name="apartment-view" id="view-cards" value="cards" checked>
                    <label class="btn btn-outline-secondary" for="view-cards" title="Панельки"><i class="bi bi-grid-3x2-gap"></i></label>
                    <input type="radio" class="btn-check" name="apartment-view" id="view-list" value="list">
                    <label class="btn btn-outline-secondary" for="view-list" title="Список"><i class="bi bi-list-ul"></i></label>
                </div>
            </div>
        </div>

        {{-- Вид панельками --}}
        <div id="apartment-cards-wrap" class="apartment-view-wrap">
            <div class="row g-3" id="apartment-cards">
                @forelse($project->apartments as $apt)
                <div class="col-md-6 col-lg-4 apartment-item" data-status="{{ $apt->status }}" data-number="{{ $apt->apartment_number }}" data-number-int="{{ is_numeric($apt->apartment_number) ? (int)$apt->apartment_number : 999999 }}" data-floor="{{ $apt->floor ?? '' }}" data-area="{{ $apt->living_area ?? '' }}" data-rooms="{{ $apt->rooms_count ?? '' }}">
                    <div class="card border-0 shadow-sm h-100">
                        @if($apt->layout_photo_url)
                            <img src="{{ $apt->layout_photo_url }}" class="card-img-top" alt="Планировка" style="height: 140px; object-fit: cover;">
                        @else
                            <div class="bg-light d-flex align-items-center justify-content-center card-img-top" style="height: 140px;"><i class="bi bi-image text-muted fs-1"></i></div>
                        @endif
                        <div class="card-body">
                            <h6 class="card-title mb-1">Квартира № {{ $apt->apartment_number }}</h6>
                            <span class="badge bg-{{ $apt->status === 'sold' ? 'secondary' : ($apt->status === 'in_pledge' ? 'warning text-dark' : 'success') }}">{{ $apt->status_label }}</span>
                            <p class="small text-muted mb-0 mt-1">
                                @if($apt->floor !== null) Этаж {{ $apt->floor }} · @endif
                                @if($apt->rooms_count) {{ $apt->rooms_count }} комн. · @endif
                                @if($apt->living_area) {{ $apt->living_area }} м² @endif
                            </p>
                            @if($apt->owner_data)
                            <p class="small mb-0 mt-1"><span class="text-muted">Владелец:</span> {{ Str::limit(strip_tags($apt->owner_data), 35) }}</p>
                            @endif
                            <a href="{{ route('cabinet.projects.apartments.show', [$project, $apt]) }}" class="btn btn-sm btn-outline-primary mt-2">Карточка</a>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12 apartment-empty">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center text-muted py-4">Нет квартир. Создайте карточку квартиры.</div>
                    </div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Вид списком --}}
        <div id="apartment-list-wrap" class="apartment-view-wrap d-none">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="cabinet-apartments-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="apartment-sort" data-sort="number" style="cursor:pointer" title="Сортировать">№ <i class="bi bi-arrow-down-up small"></i></th>
                                    <th class="apartment-sort" data-sort="status" style="cursor:pointer" title="Сортировать">Статус <i class="bi bi-arrow-down-up small"></i></th>
                                    <th class="apartment-sort" data-sort="floor" style="cursor:pointer" title="Сортировать">Этаж <i class="bi bi-arrow-down-up small"></i></th>
                                    <th class="apartment-sort" data-sort="rooms" style="cursor:pointer" title="Сортировать">Комнат <i class="bi bi-arrow-down-up small"></i></th>
                                    <th class="apartment-sort" data-sort="area" style="cursor:pointer" title="Сортировать">Площадь, м² <i class="bi bi-arrow-down-up small"></i></th>
                                    <th>Владелец</th>
                                    <th style="width: 100px;"></th>
                                </tr>
                            </thead>
                            <tbody id="apartment-list">
                                @forelse($project->apartments as $apt)
                                <tr class="apartment-item" data-status="{{ $apt->status }}" data-number="{{ $apt->apartment_number }}" data-number-int="{{ is_numeric($apt->apartment_number) ? (int)$apt->apartment_number : 999999 }}" data-floor="{{ $apt->floor ?? '' }}" data-area="{{ $apt->living_area ?? '' }}" data-rooms="{{ $apt->rooms_count ?? '' }}">
                                    <td>{{ $apt->apartment_number }}</td>
                                    <td><span class="badge bg-{{ $apt->status === 'sold' ? 'secondary' : ($apt->status === 'in_pledge' ? 'warning text-dark' : 'success') }}">{{ $apt->status_label }}</span></td>
                                    <td>{{ $apt->floor !== null ? $apt->floor : '—' }}</td>
                                    <td>{{ $apt->rooms_count ?: '—' }}</td>
                                    <td>{{ $apt->living_area ? number_format((float)$apt->living_area, 1) : '—' }}</td>
                                    <td class="small text-muted">{{ $apt->owner_data ? Str::limit(strip_tags($apt->owner_data), 40) : '—' }}</td>
                                    <td><a href="{{ route('cabinet.projects.apartments.show', [$project, $apt]) }}" class="btn btn-sm btn-outline-primary">Карточка</a></td>
                                </tr>
                                @empty
                                <tr class="apartment-empty"><td colspan="7" class="text-muted text-center py-4">Нет квартир. Создайте карточку квартиры.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-muted small mt-2 d-none" id="apartment-filter-empty">Нет квартир по выбранному фильтру.</p>
    </div>

    <script>
    (function() {
        var filterEl = document.getElementById('apartment-filter');
        var viewCardsWrap = document.getElementById('apartment-cards-wrap');
        var viewListWrap = document.getElementById('apartment-list-wrap');
        var filterEmptyEl = document.getElementById('apartment-filter-empty');
        var viewKey = 'cabinet_apartment_view';
        var filterKey = 'cabinet_apartment_filter';

        function getView() { return document.querySelector('input[name="apartment-view"]:checked')?.value || 'cards'; }
        function getFilter() { return (filterEl && filterEl.value) || ''; }

        function applyFilter() {
            var status = getFilter();
            var cards = document.querySelectorAll('#apartment-cards .apartment-item');
            var rows = document.querySelectorAll('#apartment-list .apartment-item');
            var emptyCards = document.querySelector('#apartment-cards .apartment-empty');
            var emptyRows = document.querySelector('#apartment-list .apartment-empty');
            var visibleCount = 0;
            cards.forEach(function(el) {
                var show = !status || el.getAttribute('data-status') === status;
                el.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });
            rows.forEach(function(el) {
                var show = !status || el.getAttribute('data-status') === status;
                el.style.display = show ? '' : 'none';
            });
            if (emptyCards) emptyCards.style.display = visibleCount ? 'none' : '';
            if (emptyRows) emptyRows.style.display = visibleCount ? 'none' : '';
            if (filterEmptyEl) filterEmptyEl.classList.toggle('d-none', !status || visibleCount > 0);
            try { localStorage.setItem(filterKey, status); } catch (e) {}
        }

        function applyView() {
            var view = getView();
            if (view === 'list') {
                viewCardsWrap.classList.add('d-none');
                viewListWrap.classList.remove('d-none');
            } else {
                viewCardsWrap.classList.remove('d-none');
                viewListWrap.classList.add('d-none');
            }
            try { localStorage.setItem(viewKey, view); } catch (e) {}
        }

        var sortState = { key: 'number', dir: 1 };
        var statusOrder = { available: 1, in_pledge: 2, sold: 3 };
        function num(v) { var n = parseFloat(v); return isNaN(n) ? 0 : n; }
        function getSortKey(el, key) {
            if (key === 'number') return parseInt(el.getAttribute('data-number-int'), 10) || num(el.getAttribute('data-number')) || 0;
            if (key === 'floor') return num(el.getAttribute('data-floor'));
            if (key === 'area') return num(el.getAttribute('data-area'));
            if (key === 'rooms') return num(el.getAttribute('data-rooms'));
            if (key === 'status') return statusOrder[el.getAttribute('data-status')] || 0;
            return 0;
        }
        function applySort(key, dir) {
            function doSort(parent, selector) {
                var items = [].slice.call(parent.querySelectorAll(selector));
                if (!items.length) return;
                items.sort(function(a, b) {
                    var va = getSortKey(a, key), vb = getSortKey(b, key);
                    if (key === 'number' && va === vb) {
                        var na = (a.getAttribute('data-number') || '').toString(), nb = (b.getAttribute('data-number') || '').toString();
                        return dir * (na.localeCompare(nb, undefined, { numeric: true }) || 0);
                    }
                    if (va < vb) return -dir;
                    if (va > vb) return dir;
                    return 0;
                });
                items.forEach(function(r) { parent.appendChild(r); });
            }
            doSort(document.getElementById('apartment-cards'), '.apartment-item');
            doSort(document.getElementById('apartment-list'), '.apartment-item');
        }
        var cabinetTable = document.getElementById('cabinet-apartments-table');
        if (cabinetTable) {
            cabinetTable.querySelectorAll('.apartment-sort').forEach(function(th) {
                th.addEventListener('click', function() {
                    var key = this.getAttribute('data-sort');
                    if (!key) return;
                    sortState.dir = (sortState.key === key ? -sortState.dir : 1);
                    sortState.key = key;
                    applySort(key, sortState.dir);
                });
            });
        }

        if (filterEl) filterEl.addEventListener('change', applyFilter);
        document.querySelectorAll('input[name="apartment-view"]').forEach(function(radio) {
            radio.addEventListener('change', applyView);
        });

        try {
            var savedView = localStorage.getItem(viewKey);
            if (savedView === 'list') document.getElementById('view-list').checked = true;
            var savedFilter = localStorage.getItem(filterKey);
            if (savedFilter && filterEl) filterEl.value = savedFilter;
        } catch (e) {}
        applyView();
        applyFilter();
    })();
    </script>

    {{-- Вкладка: Рабочая документация --}}
    <div class="tab-pane fade" id="docs" role="tabpanel">
        <div class="mb-4">
            <button type="button" class="btn btn-primary" data-bs-toggle="collapse" data-bs-target="#add-document-form" aria-expanded="false"><i class="bi bi-plus-lg me-1"></i> Добавить документ</button>
            <div class="collapse mt-3" id="add-document-form">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Новый документ</h6>
                        <form method="post" action="{{ route('cabinet.projects.documents.store', $project) }}" enctype="multipart/form-data">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small mb-0">Название</label>
                                    <input type="text" name="name" class="form-control form-control-sm" placeholder="Например: Договор" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-0">Файл (необязательно)</label>
                                    <input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.zip">
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-primary btn-sm">Добавить</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-3">
            @forelse($project->documents ?? [] as $doc)
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="card-title mb-1">{{ $doc->name }}</h6>
                        <p class="small text-muted mb-2">Добавлен: {{ $doc->created_at->format('d.m.Y H:i') }}</p>
                        @if($doc->file_url)
                        <p class="small mb-2">
                            <a href="{{ $doc->file_url }}" target="_blank" rel="noopener" class="text-decoration-none"><i class="bi bi-file-earmark-arrow-down me-1"></i>{{ $doc->file_name }}</a>
                        </p>
                        @else
                        <p class="small text-muted mb-2">Файл не прикреплён</p>
                        @endif
                        <div class="d-flex gap-1 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#edit-doc-{{ $doc->id }}" aria-expanded="false">Изменить</button>
                            <form method="post" action="{{ route('cabinet.projects.documents.destroy', [$project, $doc]) }}" class="d-inline" onsubmit="return confirm('Удалить документ?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Удалить</button>
                            </form>
                        </div>
                        <div class="collapse mt-2" id="edit-doc-{{ $doc->id }}">
                            <form method="post" action="{{ route('cabinet.projects.documents.update', [$project, $doc]) }}" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <div class="mb-2">
                                    <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $doc->name) }}" required>
                                </div>
                                <div class="mb-2">
                                    <input type="file" name="file" class="form-control form-control-sm" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.zip">
                                    <small class="text-muted">Оставьте пустым, чтобы не менять файл</small>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary">Сохранить</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center text-muted py-4">Нет документов. Нажмите «Добавить документ».</div>
                </div>
            </div>
            @endforelse
        </div>
        <hr class="my-4">
        <p class="text-muted small mb-2">Дополнительные поля (название — значение)</p>
        <form method="post" action="{{ route('cabinet.projects.document-fields.store', $project) }}" class="row g-2 align-items-end mb-2">
            @csrf
            <div class="col-auto">
                <input type="text" name="name" class="form-control form-control-sm" placeholder="Название поля" required>
            </div>
            <div class="col-auto">
                <input type="text" name="value" class="form-control form-control-sm" placeholder="Значение">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary btn-sm">Добавить поле</button>
            </div>
        </form>
        @if(($project->documentFields ?? collect())->isNotEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    @foreach($project->documentFields ?? [] as $field)
                    <tr>
                        <td>{{ $field->name }}</td>
                        <td>{{ Str::limit($field->value, 200) }}</td>
                        <td class="text-end" style="width: 80px;">
                            <form method="post" action="{{ route('cabinet.projects.document-fields.destroy', [$project, $field]) }}" class="d-inline" onsubmit="return confirm('Удалить поле?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- Вкладка: Задачи --}}
    <div class="tab-pane fade" id="tasks" role="tabpanel">
        <h5 class="mb-3">Задачи по проекту</h5>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Название</th>
                                <th>Статус</th>
                                <th>Ответственный</th>
                                <th>Дата окончания</th>
                                <th class="text-end">Бюджет</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($project->tasks as $t)
                            <tr>
                                <td>{{ Str::limit($t->title, 60) }}</td>
                                <td><span class="badge bg-secondary">{{ $t->status_label }}</span></td>
                                <td>{{ $t->client ? $t->client->full_name : '—' }}</td>
                                <td>{{ $t->due_date ? $t->due_date->format('d.m.Y') : '—' }}</td>
                                <td class="text-end">{{ $t->budget !== null ? number_format($t->budget, 2) . ' ' . \App\Models\Setting::get('currency', 'RUB') : '—' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-muted text-center py-4">Нет задач по этому проекту. Создайте задачу в разделе «Задачи» и укажите проект.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
.stages-pastel-not_started { background: #e9ecef; }
.stages-pastel-in_progress { background: #cfe2ff; }
.stages-pastel-completed { background: #d1e7dd; }
.stage-card:hover { background: #f8f9fa !important; }
</style>
@endpush
@push('scripts')
<script>
(function() {
    var modalEl = document.getElementById('stageDetailModal');
    var modalBody = document.getElementById('stageDetailModalBody');
    var modalTitle = document.getElementById('stageDetailModalLabel');
    var kanban = document.querySelector('.stages-kanban');
    if (!modalEl || !modalBody || !kanban) return;
    var baseUrl = kanban.dataset.baseUrl || '';
    var csrf = kanban.dataset.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';

    function openStageModal(stageId, stageName) {
        modalTitle.textContent = stageName || 'Этап';
        modalBody.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Загрузка...</div>';
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
        fetch(baseUrl + '/stages/' + stageId, { headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                modalBody.innerHTML = html;
                bindStageModalActions(stageId);
            })
            .catch(function() {
                modalBody.innerHTML = '<div class="alert alert-danger">Не удалось загрузить данные.</div>';
            });
    }

    function bindStageModalActions(stageId) {
        var statusBtn = document.getElementById('stage-modal-status-btn');
        var statusSelect = document.getElementById('stage-modal-status-select');
        var statusBadge = document.getElementById('stage-modal-status-badge');
        if (statusBtn && statusSelect) {
            statusBtn.onclick = function() {
                var status = statusSelect.value;
                statusBtn.disabled = true;
                fetch(baseUrl + '/stages/' + stageId, {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ status: status })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok && statusBadge) { statusBadge.textContent = data.status_label; statusBadge.className = 'badge bg-' + (data.status === 'completed' ? 'success' : (data.status === 'in_progress' ? 'primary' : 'secondary')); }
                })
                .finally(function() { statusBtn.disabled = false; });
            };
        }
        var photoForm = document.getElementById('stage-photo-upload-form');
        if (photoForm) {
            photoForm.onsubmit = function(e) {
                e.preventDefault();
                var input = document.getElementById('stage-photo-input');
                if (!input || !input.files.length) return;
                var fd = new FormData();
                fd.append('photo', input.files[0]);
                fd.append('_token', csrf);
                var btn = photoForm.querySelector('button[type="submit"]');
                btn.disabled = true;
                fetch(baseUrl + '/stages/' + stageId + '/photos', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok && data.url) {
                        var gallery = document.getElementById('stage-photos-gallery');
                        var empty = document.getElementById('stage-photos-empty');
                        if (empty) empty.style.display = 'none';
                        var col = document.createElement('div');
                        col.className = 'col-6 col-md-4';
                        col.innerHTML = '<a href="' + data.url + '" target="_blank" rel="noopener" class="d-block rounded overflow-hidden border"><img src="' + data.url + '" alt="" class="img-fluid w-100" style="height: 120px; object-fit: cover;"></a>';
                        gallery.appendChild(col);
                        input.value = '';
                    }
                })
                .finally(function() { btn.disabled = false; });
            };
        }
        var commentForm = document.getElementById('stage-comment-form');
        if (commentForm) {
            commentForm.onsubmit = function(e) {
                e.preventDefault();
                var ta = commentForm.querySelector('textarea[name="body"]');
                var body = ta && ta.value ? ta.value.trim() : '';
                if (!body) return;
                var btn = commentForm.querySelector('button[type="submit"]');
                btn.disabled = true;
                fetch(baseUrl + '/stages/' + stageId + '/comments', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ body: body, _token: csrf })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ok) {
                        var list = document.getElementById('stage-comments-list');
                        var empty = document.getElementById('stage-comments-empty');
                        if (empty) empty.style.display = 'none';
                        var div = document.createElement('div');
                        div.className = 'border-bottom pb-2 mb-2 small';
                        div.innerHTML = '<strong>' + (data.client_name || '') + '</strong> <span class="text-muted ms-1">' + (data.created_at || '') + '</span><div class="mt-1">' + (data.body || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '</div>';
                        list.insertBefore(div, list.firstChild);
                        ta.value = '';
                    }
                })
                .finally(function() { btn.disabled = false; });
            };
        }
        modalBody.querySelectorAll('.cabinet-work-row').forEach(function(row) {
            row.addEventListener('click', function() {
                var id = row.dataset.workId;
                var detail = document.getElementById('cabinet-work-detail-' + id);
                var icon = row.querySelector('.cabinet-work-toggle');
                if (detail && detail.classList.contains('d-none')) {
                    detail.classList.remove('d-none');
                    if (icon) icon.classList.replace('bi-chevron-right', 'bi-chevron-down');
                } else if (detail) {
                    detail.classList.add('d-none');
                    if (icon) icon.classList.replace('bi-chevron-down', 'bi-chevron-right');
                }
            });
        });
    }

    document.querySelectorAll('.stage-card').forEach(function(el) {
        el.addEventListener('click', function() {
            var id = el.dataset.stageId;
            var name = el.dataset.stageName;
            if (id) openStageModal(id, name);
        });
    });
    document.querySelectorAll('.stage-card-link').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            var id = el.dataset.stageId;
            if (id) openStageModal(id, el.textContent.trim());
        });
    });
})();
</script>
@endpush
@endsection
