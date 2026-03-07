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
</div>
@endsection
