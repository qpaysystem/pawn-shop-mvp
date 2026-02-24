@extends('layouts.app')

@section('title', $client->full_name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">{{ $client->full_name }}</h1>
    <div>
        <a href="{{ route('clients.edit', $client) }}" class="btn btn-outline-primary">Изменить</a>
    </div>
</div>
<div class="card mb-4">
    <div class="card-body">
        <p><strong>Тип:</strong> @if($client->isLegal())<span class="badge bg-secondary">Юридическое лицо</span>@else<span class="badge bg-light text-dark">Физическое лицо</span>@endif</p>
        @if($client->isLegal())
            <p><strong>Наименование:</strong> {{ $client->legal_name }}</p>
            @if($client->inn)<p><strong>ИНН:</strong> {{ $client->inn }}</p>@endif
            @if($client->kpp)<p><strong>КПП:</strong> {{ $client->kpp }}</p>@endif
            @if($client->legal_address)<p><strong>Юридический адрес:</strong> {{ $client->legal_address }}</p>@endif
        @endif
        <p><strong>Телефон:</strong> {{ $client->phone }}</p>
        @if($client->email)<p><strong>Email:</strong> {{ $client->email }}</p>@endif
        @if(!$client->isLegal() && $client->passport_data)<p><strong>Паспорт:</strong> {{ $client->passport_data }}</p>@endif
        @if($client->notes)<p><strong>Заметки:</strong> {{ $client->notes }}</p>@endif
        @if($client->blacklist_flag)<p><span class="badge bg-danger">В чёрном списке</span></p>@endif
    </div>
</div>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Данные из 1С</strong>
        <form method="post" action="{{ route('clients.sync-lmb', $client) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-cloud-download"></i> Загрузить из 1С</button>
        </form>
    </div>
    <div class="card-body">
        @if($client->lmb_data)
            <p class="mb-1"><strong>Код в 1С (user_uid):</strong> {{ $client->lmb_data['user_uid'] ?? '—' }}</p>
            <p class="mb-1"><strong>ФИО / first_name:</strong> {{ $client->lmb_data['first_name'] ?? '—' }}</p>
            <p class="mb-1"><strong>Имя (second_name):</strong> {{ $client->lmb_data['second_name'] ?? '—' }}</p>
            <p class="mb-1"><strong>Отчество (last_name):</strong> {{ $client->lmb_data['last_name'] ?? '—' }}</p>
            <p class="mb-0"><strong>Телефон:</strong> {{ $client->lmb_data['phone'] ?? '—' }}</p>
        @else
            <p class="text-muted mb-0">Данные не загружались. Нажмите «Загрузить из 1С», чтобы получить данные контрагента по номеру телефона.</p>
        @endif
    </div>
</div>
<div class="card mb-4">
    <div class="card-body py-3">
        <strong>Кассовый баланс клиента:</strong>
        <span class="fs-5 ms-2 {{ $client->cash_balance >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($client->cash_balance, 0, ',', ' ') }} ₽</span>
        <span class="text-muted small ms-2">(приходы − расходы по операциям с клиентом)</span>
        <a href="{{ route('cash.index', ['client_id' => $client->id]) }}" class="btn btn-sm btn-outline-primary ms-2">Операции по кассе →</a>
    </div>
</div>
<h5 class="mb-3">Договоры залога</h5>
@if($client->pawnContracts->isEmpty())
    <p class="text-muted">Нет договоров залога.</p>
@else
    <table class="table table-sm">
        <thead><tr><th>№ договора</th><th>Товар</th><th>Сумма займа</th><th>Сумма на выкуп</th><th>Выкуп</th><th></th></tr></thead>
        <tbody>
            @foreach($client->pawnContracts as $pc)
            <tr>
                <td>{{ $pc->contract_number }}</td>
                <td>{{ $pc->item->name }}</td>
                <td>{{ number_format($pc->loan_amount, 0, '', ' ') }} ₽</td>
                <td>{{ number_format($pc->redemption_amount, 0, '', ' ') }} ₽</td>
                <td>@if($pc->is_redeemed)<span class="badge bg-success">Выкуплен</span>@else<span class="badge bg-warning">Активен</span>@endif</td>
                <td><a href="{{ route('pawn-contracts.show', $pc) }}">Подробнее</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endif
<h5 class="mb-3 mt-4">Договоры комиссии</h5>
@if($client->commissionContracts->isEmpty())
    <p class="text-muted">Нет договоров комиссии.</p>
@else
    <table class="table table-sm">
        <thead><tr><th>№ договора</th><th>Товар</th><th>Цена продажи</th><th>Продано</th><th></th></tr></thead>
        <tbody>
            @foreach($client->commissionContracts as $cc)
            <tr>
                <td>{{ $cc->contract_number }}</td>
                <td>{{ $cc->item->name }}</td>
                <td>{{ $cc->seller_price ? number_format($cc->seller_price, 0, '', ' ') . ' ₽' : '—' }}</td>
                <td>@if($cc->is_sold)<span class="badge bg-success">Да</span>@else<span class="badge bg-warning">Нет</span>@endif</td>
                <td><a href="{{ route('commission-contracts.show', $cc) }}">Подробнее</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endif
<h5 class="mb-3 mt-4">Договоры скупки</h5>
@if($client->purchaseContracts->isEmpty())
    <p class="text-muted">Нет договоров скупки.</p>
@else
    <table class="table table-sm">
        <thead><tr><th>№ договора</th><th>Товар</th><th>Сумма скупки</th><th>Дата</th><th></th></tr></thead>
        <tbody>
            @foreach($client->purchaseContracts as $puc)
            <tr>
                <td>{{ $puc->contract_number }}</td>
                <td>{{ $puc->item->name }}</td>
                <td>{{ number_format($puc->purchase_amount, 0, '', ' ') }} ₽</td>
                <td>{{ \Carbon\Carbon::parse($puc->purchase_date)->format('d.m.Y') }}</td>
                <td><a href="{{ route('purchase-contracts.show', $puc) }}">Подробнее</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endif
<h5 class="mb-3 mt-4">Обращения (колл-центр)</h5>
@if($client->callCenterContacts->isEmpty())
    <p class="text-muted">Нет обращений.</p>
@else
    <table class="table table-sm">
        <thead><tr><th>Дата</th><th>Канал</th><th>Исход</th><th>Сделка</th><th></th></tr></thead>
        <tbody>
            @foreach($client->callCenterContacts as $ccc)
            <tr>
                <td>{{ $ccc->contact_date ? \Carbon\Carbon::parse($ccc->contact_date)->format('d.m.Y H:i') : '—' }}</td>
                <td>{{ $ccc->channel_label }}</td>
                <td>{{ $ccc->outcome_label }}</td>
                <td>
                    @if($ccc->pawn_contract_id)
                        <a href="{{ route('pawn-contracts.show', $ccc->pawnContract) }}">{{ $ccc->pawnContract->contract_number }}</a>
                    @elseif($ccc->purchase_contract_id)
                        <a href="{{ route('purchase-contracts.show', $ccc->purchaseContract) }}">{{ $ccc->purchaseContract->contract_number }}</a>
                    @elseif($ccc->commission_contract_id)
                        <a href="{{ route('commission-contracts.show', $ccc->commissionContract) }}">{{ $ccc->commissionContract->contract_number }}</a>
                    @else
                        —
                    @endif
                </td>
                <td><a href="{{ route('call-center.show', $ccc) }}">Подробнее</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endif
<a href="{{ route('call-center.create', ['client_id' => $client->id]) }}" class="btn btn-sm btn-outline-primary">Зафиксировать обращение</a>
<a href="{{ route('call-center.index', ['client_id' => $client->id]) }}" class="btn btn-sm btn-outline-secondary">Все обращения клиента</a>
<div class="mt-3">
<a href="{{ route('clients.index') }}" class="btn btn-secondary">К списку клиентов</a>
</div>
@endsection
