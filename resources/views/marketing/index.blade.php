@extends('layouts.app')

@section('title', 'Маркетинг')

@section('content')
<h1 class="h4 mb-4">Маркетинг</h1>

<form method="get" action="{{ route('marketing.index') }}" class="row g-2 mb-4">
    <div class="col-auto">
        <label class="form-label visually-hidden">Дата с</label>
        <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}" placeholder="С">
    </div>
    <div class="col-auto">
        <label class="form-label visually-hidden">По</label>
        <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}" placeholder="По">
    </div>
    <div class="col-auto d-flex align-items-end">
        <button type="submit" class="btn btn-primary">Показать</button>
        <a href="{{ route('marketing.index') }}" class="btn btn-outline-secondary ms-1">Сбросить</a>
    </div>
</form>

<ul class="nav nav-tabs mb-3" id="marketingTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="sources-tab" data-bs-toggle="tab" data-bs-target="#sources" type="button" role="tab">Источники трафика</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="funnel-tab" data-bs-toggle="tab" data-bs-target="#funnel" type="button" role="tab">Воронка</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="effectiveness-tab" data-bs-toggle="tab" data-bs-target="#effectiveness" type="button" role="tab">Эффективность каналов</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="dgis-tab" data-bs-toggle="tab" data-bs-target="#dgis" type="button" role="tab">2ГИС</button>
    </li>
</ul>

<div class="tab-content" id="marketingTabsContent">
    <div class="tab-pane fade show active" id="sources" role="tabpanel">
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Источник</th>
                            <th class="text-end">Клиентов</th>
                            <th class="text-end">На этапе «Сделка»</th>
                            <th class="text-end">С договорами</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bySource as $row)
                        <tr>
                            <td>{{ $row['source']->name }}</td>
                            <td class="text-end">{{ $row['total'] }}</td>
                            <td class="text-end">{{ $row['deals_stage'] }}</td>
                            <td class="text-end">{{ $row['with_contracts'] }}</td>
                        </tr>
                        @endforeach
                        <tr class="table-secondary">
                            <td>Без источника</td>
                            <td class="text-end">{{ $noSource }}</td>
                            <td class="text-end">—</td>
                            <td class="text-end">{{ $noSourceDeals }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="small text-muted mt-2">Укажите источник трафика и этап воронки в карточке клиента (редактирование).</p>
    </div>

    <div class="tab-pane fade" id="funnel" role="tabpanel">
        <div class="card mb-3">
            <div class="card-header bg-light"><strong>Воронка по этапам</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($funnelStages as $code => $label)
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 text-center">
                            <div class="small text-muted">{{ $label }}</div>
                            <div class="h4 mb-0">{{ $funnelTotal[$code] ?? 0 }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="mt-3 pt-3 border-top">
                    <strong>Клиентов с хотя бы одним договором</strong> (залог / комиссия / скупка): <strong>{{ $clientsWithAnyContract }}</strong>
                </div>
            </div>
        </div>
        <p class="small text-muted">Этап воронки задаётся в карточке клиента. «Сделка» можно выставлять вручную или считать по факту наличия договоров.</p>
    </div>

    <div class="tab-pane fade" id="effectiveness" role="tabpanel">
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Канал</th>
                            <th class="text-end">Клиентов</th>
                            <th class="text-end">Сделок (договоры)</th>
                            <th class="text-end">Конверсия, %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($bySource as $row)
                        <tr>
                            <td>{{ $row['source']->name }}</td>
                            <td class="text-end">{{ $row['total'] }}</td>
                            <td class="text-end">{{ $row['with_contracts'] }}</td>
                            <td class="text-end">{{ $row['conversion'] }}%</td>
                        </tr>
                        @endforeach
                        <tr class="table-secondary">
                            <td>Без источника</td>
                            <td class="text-end">{{ $noSource }}</td>
                            <td class="text-end">{{ $noSourceDeals }}</td>
                            <td class="text-end">{{ $noSource > 0 ? round($noSourceDeals / $noSource * 100, 1) : 0 }}%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="small text-muted mt-2">Конверсия = (клиенты с договорами залога/комиссии/купли) / (все клиенты по каналу) × 100%.</p>
    </div>

    <div class="tab-pane fade" id="dgis" role="tabpanel">
        @if($dgisBranch ?? null)
            <div class="card mb-3">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <strong>Карточка организации в 2ГИС</strong>
                    <form method="post" action="{{ route('marketing.refresh-2gis') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-clockwise"></i> Обновить данные</button>
                    </form>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>{{ $dgisBranch['name'] }}</strong></p>
                    @if(!empty($dgisBranch['address']))<p class="mb-1 text-muted small">{{ $dgisBranch['address'] }}</p>@endif
                    <p class="mb-1">
                        @if(isset($dgisBranch['rating']))<span class="badge bg-warning text-dark">Рейтинг: {{ number_format($dgisBranch['rating'], 1) }}</span>@endif
                        @if(isset($dgisBranch['reviews_count']))<span class="badge bg-secondary">Отзывов: {{ $dgisBranch['reviews_count'] }}</span>@endif
                    </p>
                    @if(!empty($dgisBranch['link']))<p class="mb-0"><a href="{{ $dgisBranch['link'] }}" target="_blank" rel="noopener">Открыть в 2ГИС <i class="bi bi-box-arrow-up-right"></i></a></p>@endif
                </div>
            </div>
        @else
            <div class="alert alert-info">
                Данные карточки 2ГИС подтягиваются по API. Укажите в <code>.env</code>: <code>DGIS_API_KEY</code> (ключ из <a href="https://platform.2gis.ru/" target="_blank" rel="noopener">Platform Manager</a>) и <code>DGIS_BRANCH_ID</code> (ID филиала из ссылки на карточку в 2ГИС).
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header bg-light"><strong>Просмотры и звонки по дням</strong></div>
            <div class="card-body">
                <p class="small text-muted">Просмотры карточки и звонки из 2ГИС в публичный API не отдаются. Добавляйте данные вручную из <a href="https://account.2gis.com/" target="_blank" rel="noopener">кабинета 2ГИС для бизнеса</a> или после экспорта.</p>
                <form method="post" action="{{ route('marketing.2gis-stats.store') }}" class="row g-2 mb-3">
                    @csrf
                    <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                    <input type="hidden" name="date_to" value="{{ $dateTo }}">
                    <div class="col-auto"><label class="form-label visually-hidden">Дата</label><input type="date" name="date" class="form-control" required></div>
                    <div class="col-auto"><label class="form-label visually-hidden">Просмотры</label><input type="number" name="views_count" class="form-control" min="0" placeholder="Просмотры" value="0"></div>
                    <div class="col-auto"><label class="form-label visually-hidden">Звонки</label><input type="number" name="calls_count" class="form-control" min="0" placeholder="Звонки" value="0"></div>
                    <div class="col-auto"><label class="form-label visually-hidden">Комментарий</label><input type="text" name="comment" class="form-control" placeholder="Комментарий"></div>
                    <div class="col-auto d-flex align-items-end"><button type="submit" class="btn btn-primary">Добавить</button></div>
                </form>
                <p class="mb-2"><strong>За выбранный период:</strong> просмотров {{ $dgisTotals['views'] ?? 0 }}, звонков {{ $dgisTotals['calls'] ?? 0 }}</p>
                @if(($dgisStats ?? collect())->isNotEmpty())
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Дата</th><th class="text-end">Просмотры</th><th class="text-end">Звонки</th><th>Комментарий</th></tr></thead>
                        <tbody>
                            @foreach($dgisStats as $s)
                            <tr>
                                <td>{{ $s->date->format('d.m.Y') }}</td>
                                <td class="text-end">{{ $s->views_count }}</td>
                                <td class="text-end">{{ $s->calls_count }}</td>
                                <td class="small text-muted">{{ Str::limit($s->comment, 40) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted small mb-0">Нет записей. Добавьте данные выше.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    if (window.location.hash !== '#dgis') return;
    var tab = document.getElementById('dgis-tab');
    if (tab) {
        var bsTab = bootstrap.Tab.getOrCreateInstance(tab);
        if (bsTab) bsTab.show();
    }
})();
</script>
@endsection
