@extends('cabinet.layout')
@section('title', 'Календарь и напоминания')
@section('content')
<h1 class="h4 mb-4"><i class="bi bi-calendar3 me-2"></i>Задачи в календаре iPhone</h1>

<p class="text-muted mb-4">Подпишитесь на календарь — ваши задачи (где вы указаны ответственным) будут отображаться в приложении «Календарь» на iPhone и обновляться автоматически.</p>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="h6 mb-3">Ссылка для подписки</h5>
        <div class="input-group">
            <input type="text" class="form-control font-monospace small" id="feed-url" value="{{ $feedUrl }}" readonly>
            <button type="button" class="btn btn-outline-primary" id="copy-btn" title="Копировать"><i class="bi bi-clipboard"></i> Копировать</button>
        </div>
        <p class="small text-muted mt-2 mb-0">Не передавайте эту ссылку другим — по ней доступны ваши задачи.</p>
        <p class="small text-warning mt-2 mb-0">Для iPhone ссылка должна начинаться с <strong>https://</strong>. Если сайт открывается по http, подписка может не сработать.</p>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h5 class="h6 mb-3">Как добавить календарь на iPhone</h5>
        <ol class="mb-0 ps-3">
            <li class="mb-2">Откройте приложение <strong>«Настройки»</strong> → <strong>«Календарь»</strong> → <strong>«Учётные записи»</strong> → <strong>«Добавить учётную запись»</strong>.</li>
            <li class="mb-2">Выберите <strong>«Другое»</strong> → <strong>«Добавить подписанный календарь»</strong>.</li>
            <li class="mb-2">Вставьте скопированную ссылку и нажмите <strong>«Далее»</strong>.</li>
            <li>Готово. Задачи появятся в приложении «Календарь»; при изменении срока задачи в личном кабинете календарь обновится при следующей синхронизации.</li>
        </ol>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h5 class="h6 mb-2">Альтернатива: через приложение «Календарь»</h5>
        <p class="small text-muted mb-0">В приложении «Календарь» нажмите <strong>«Календари»</strong> внизу → <strong>«Добавить календарь»</strong> → <strong>«Подписаться по URL»</strong> и вставьте ссылку выше.</p>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var btn = document.getElementById('copy-btn');
    var input = document.getElementById('feed-url');
    if (!btn || !input) return;
    btn.addEventListener('click', function() {
        input.select();
        input.setSelectionRange(0, 99999);
        try {
            navigator.clipboard.writeText(input.value);
            btn.innerHTML = '<i class="bi bi-check"></i> Скопировано';
            btn.classList.remove('btn-outline-primary');
            btn.classList.add('btn-success');
            setTimeout(function() {
                btn.innerHTML = '<i class="bi bi-clipboard"></i> Копировать';
                btn.classList.add('btn-outline-primary');
                btn.classList.remove('btn-success');
            }, 2000);
        } catch (e) {}
    });
})();
</script>
@endpush
@endsection
