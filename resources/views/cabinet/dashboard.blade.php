@extends('cabinet.layout')
@section('title', 'Главная')
@section('content')
<h1 class="h4 mb-4">Добро пожаловать, {{ $client->full_name }}</h1>

@if(config('services.vapid.public') && request()->secure())
<div class="alert alert-info alert-dismissible fade show mb-4 py-2" id="push-prompt-wrap" role="alert">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span><i class="bi bi-bell me-2"></i>Получать уведомления о новых транзакциях?</span>
        <div>
            <button type="button" class="btn btn-sm btn-primary me-1" id="push-enable-btn">Включить</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="alert" aria-label="Закрыть" id="push-dismiss">&times;</button>
        </div>
    </div>
</div>
@endif

<div class="row g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="text-muted mb-2">Баланс</h6>
                <h3 class="mb-0">{{ number_format($client->balance, 2) }} {{ \App\Models\Setting::get('currency', 'RUB') }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <a href="{{ route('cabinet.transactions') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-wallet2 fs-2 text-primary me-3"></i>
                    <div>
                        <h6 class="text-muted mb-0">Транзакции</h6>
                        <span class="small">История операций</span>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('cabinet.board') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-kanban fs-2 text-primary me-3"></i>
                    <div>
                        <h6 class="text-muted mb-0">Канбан-доска</h6>
                        <span class="small">Мои задачи</span>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('cabinet.projects.index') }}" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-folder2-open fs-2 text-primary me-3"></i>
                    <div>
                        <h6 class="text-muted mb-0">Проекты</h6>
                        <span class="small">Мои проекты</span>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

@if(config('services.vapid.public') && request()->secure())
@push('scripts')
<script>
(function() {
  var vapidMeta = document.querySelector('meta[name="vapid-public-key"]');
  if (!vapidMeta || !vapidMeta.content) return;
  var publicKey = vapidMeta.content;
  function urlBase64ToUint8Array(base64) {
    var padding = '='.repeat((4 - base64.length % 4) % 4);
    var b64 = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
    var raw = atob(b64);
    var out = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
    return out;
  }
  var btn = document.getElementById('push-enable-btn');
  var wrap = document.getElementById('push-prompt-wrap');
  if (!btn || !wrap) return;
  if ('Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window) {
    if (Notification.permission === 'granted') wrap.style.display = 'none';
    btn.addEventListener('click', function() {
      btn.disabled = true;
      Notification.requestPermission().then(function(perm) {
        if (perm !== 'granted') { btn.disabled = false; return; }
        navigator.serviceWorker.ready.then(function(reg) {
          return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(publicKey) });
        }).then(function(sub) {
          return fetch('{{ route("cabinet.push.subscribe") }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(sub.toJSON())
          });
        }).then(function(r) {
          if (r.ok) wrap.remove();
        }).catch(function() { btn.disabled = false; });
      });
    });
  } else wrap.style.display = 'none';
})();
</script>
@endpush
@endif
@endsection
