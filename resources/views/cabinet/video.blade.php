@extends('cabinet.layout')
@section('title', 'Видеоконференция')
@section('content')
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h5 class="card-title"><i class="bi bi-camera-video me-2"></i>Видеоконференция</h5>
        <p class="text-muted small">Совещание по видео между клиентами. Рекомендуется не более 4 участников. Отправьте ссылку-приглашение другим участникам.</p>

        @if($room)
            <div class="mb-3">
                <label class="form-label small fw-bold">Ссылка для приглашения (отправьте другим участникам):</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="invite-url" value="{{ $inviteUrl }}" readonly>
                    <button type="button" class="btn btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('invite-url').value); this.textContent='Скопировано'; setTimeout(() => this.textContent='Копировать', 2000);">Копировать</button>
                </div>
            </div>
            @php
                $jitsiBase = rtrim(config('services.jitsi.server_url', 'https://meet.jit.si'), '/');
            @endphp
            <div class="ratio ratio-16x9 bg-dark rounded overflow-hidden" style="max-height: 70vh;">
                <iframe
                    src="{{ $jitsiBase }}/{{ $room }}?config.startWithAudioMuted=false&config.startWithVideoMuted=false&config.subject=Встреча%20ЛК&interfaceConfig.SHOW_JITSI_WATERMARK=false&interfaceConfig.SHOW_WATERMARK_FOR_GUESTS=false"
                    allow="camera; microphone; fullscreen; display-capture"
                    allowfullscreen
                    style="border: 0; width: 100%; height: 100%;"
                    title="Видеоконференция"
                ></iframe>
            </div>
            <p class="small text-muted mt-2 mb-0">Разрешите доступ к камере и микрофону в браузере. Чтобы выйти — закройте вкладку или перейдите в другой раздел.</p>
        @else
            <p class="mb-3">Нажмите кнопку ниже, чтобы создать встречу. После этого скопируйте ссылку и отправьте её участникам (до 4 человек).</p>
            <a href="{{ route('cabinet.video', ['room' => $startRoom]) }}" class="btn btn-primary"><i class="bi bi-camera-video-fill me-1"></i> Начать встречу</a>
        @endif
    </div>
</div>
@endsection
