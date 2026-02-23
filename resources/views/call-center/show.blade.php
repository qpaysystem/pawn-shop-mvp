@extends('layouts.app')

@section('title', 'Обращение')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0">Обращение #{{ $callCenterContact->id }}</h1>
    <div>
        <a href="{{ route('call-center.edit', $callCenterContact) }}" class="btn btn-outline-primary">Изменить</a>
        <a href="{{ route('call-center.index') }}" class="btn btn-secondary">К списку</a>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Канал и контакт</div>
            <div class="card-body">
                <p><strong>Канал:</strong> {{ $callCenterContact->channel_label }}</p>
                <p><strong>Направление:</strong> {{ $callCenterContact->direction === 'incoming' ? 'Входящее' : 'Исходящее' }}</p>
                @if($callCenterContact->call_status)
                    <p><strong>Статус вызова:</strong> {{ $callCenterContact->call_status_label }}</p>
                @endif
                <p><strong>Дата:</strong> {{ $callCenterContact->contact_date ? \Carbon\Carbon::parse($callCenterContact->contact_date)->format('d.m.Y H:i') : '—' }}</p>
                <p><strong>Магазин:</strong> {{ $callCenterContact->store?->name ?? '—' }}</p>
                <hr>
                @if($callCenterContact->client_id)
                    <p><strong>Клиент:</strong> <a href="{{ route('clients.show', $callCenterContact->client) }}">{{ $callCenterContact->client->full_name }}</a></p>
                    <p><strong>Телефон:</strong> {{ $callCenterContact->client->phone ?? '—' }}</p>
                @else
                    <p><strong>Контакт:</strong> {{ $callCenterContact->contact_name ?: '—' }}</p>
                    <p><strong>Телефон:</strong> {{ $callCenterContact->contact_phone ?: '—' }}</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">Исход и сделка</div>
            <div class="card-body">
                <p><strong>Исход:</strong> {{ $callCenterContact->outcome_label }}</p>
                @if($callCenterContact->linkedContract)
                    <p><strong>Сделка:</strong>
                        @if($callCenterContact->pawn_contract_id)
                            <a href="{{ route('pawn-contracts.show', $callCenterContact->pawnContract) }}">{{ $callCenterContact->pawnContract->contract_number }}</a> (залог)
                        @elseif($callCenterContact->purchase_contract_id)
                            <a href="{{ route('purchase-contracts.show', $callCenterContact->purchaseContract) }}">{{ $callCenterContact->purchaseContract->contract_number }}</a> (скупка)
                        @elseif($callCenterContact->commission_contract_id)
                            <a href="{{ route('commission-contracts.show', $callCenterContact->commissionContract) }}">{{ $callCenterContact->commissionContract->contract_number }}</a> (комиссия)
                        @endif
                    </p>
                @endif
                @if($callCenterContact->createdByUser)
                    <p><strong>Зафиксировал:</strong> {{ $callCenterContact->createdByUser->name }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@if($callCenterContact->notes)
<div class="card mb-4">
    <div class="card-header">Заметки</div>
    <div class="card-body">{{ nl2br(e($callCenterContact->notes)) }}</div>
</div>
@endif
@if($callCenterContact->channel === 'phone' && ($callCenterContact->recording_path || ($callCenterContact->external_id && str_starts_with($callCenterContact->external_id, 'mts_'))))
<div class="card mb-4" id="recording">
    <div class="card-header">Запись разговора</div>
    <div class="card-body">
        @if($callCenterContact->recording_path)
            <audio controls preload="metadata" class="w-100" style="max-width: 400px;">
                <source src="{{ route('call-center.recording', $callCenterContact) }}" type="audio/mpeg">
                Ваш браузер не поддерживает воспроизведение.
            </audio>
            <p class="mt-2 mb-0"><a href="{{ route('call-center.recording', $callCenterContact) }}?download=1" class="btn btn-sm btn-outline-secondary">Скачать MP3</a></p>
        @elseif($callCenterContact->external_id && str_starts_with($callCenterContact->external_id, 'mts_'))
            <p class="mb-2">Запись хранится в MTS. Можно попробовать получить её по ссылке (по идентификатору звонка).</p>
            <p class="mb-2">
                <a href="{{ route('call-center.recording-mts', $callCenterContact) }}" class="btn btn-sm btn-primary" target="_blank" rel="noopener"><i class="bi bi-play-circle"></i> Слушать запись с MTS</a>
                <a href="{{ route('call-center.recording-mts', $callCenterContact) }}?download=1" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i> Скачать с MTS</a>
            </p>
            <p class="mb-0 small text-muted">
                <a href="https://vpbx.mts.ru" target="_blank" rel="noopener">Открыть историю вызовов в личном кабинете MTS</a> — там можно найти звонок по дате и прослушать запись.
            </p>
        @endif
        <hr class="my-3">
        <p class="mb-2 small text-muted">Расшифровка в текст: Whisper (распознавание речи) + DeepSeek (оформление). Нужны OPENAI_API_KEY и DEEPSEEK_API_KEY в .env.</p>
        <form method="post" action="{{ route('call-center.transcribe', $callCenterContact) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-text-left"></i> {{ $callCenterContact->recording_transcript ? 'Обновить расшифровку' : 'Транскрибировать в текст' }}</button>
        </form>
    </div>
</div>
@endif
@if($callCenterContact->recording_transcript)
<div class="card mb-4" id="transcript">
    <div class="card-header">Расшифровка разговора</div>
    <div class="card-body">
        <div class="bg-light rounded p-3" style="white-space: pre-wrap;">{{ $callCenterContact->recording_transcript }}</div>
    </div>
</div>
@endif
@endsection
