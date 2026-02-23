@extends('layouts.app')

@section('title', 'Зафиксировать обращение')

@section('content')
<h1 class="h4 mb-4">Зафиксировать обращение</h1>

<form method="post" action="{{ route('call-center.store') }}">
    @csrf
    <div class="card mb-3">
        <div class="card-header">Канал и направление</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Канал *</label>
                    <select name="channel" class="form-select" required>
                        @foreach(\App\Models\CallCenterContact::CHANNELS as $k => $v)
                            <option value="{{ $k }}" {{ old('channel') === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Направление *</label>
                    <select name="direction" class="form-select" required>
                        <option value="incoming" {{ old('direction', 'incoming') === 'incoming' ? 'selected' : '' }}>Входящее</option>
                        <option value="outgoing" {{ old('direction') === 'outgoing' ? 'selected' : '' }}>Исходящее</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Магазин</label>
                    <select name="store_id" class="form-select">
                        <option value="">—</option>
                        @foreach($stores as $s)
                            <option value="{{ $s->id }}" {{ old('store_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header">Дата и время</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Дата *</label>
                    <input type="date" name="contact_date" class="form-control" value="{{ old('contact_date', date('Y-m-d')) }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Время</label>
                    <input type="time" name="contact_time" class="form-control" value="{{ old('contact_time', date('H:i')) }}">
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header">Клиент / контакт</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Поиск клиента</label>
                <input type="text" class="form-control" id="client_search" placeholder="Телефон или ФИО...">
                <input type="hidden" name="client_id" id="client_id" value="{{ old('client_id', $presetClient?->id) }}">
                <div id="client_search_results" class="list-group mt-1" style="max-height:150px;overflow:auto;display:none;"></div>
                <small class="text-muted">Или укажите данные нового контакта ниже</small>
            </div>
                @if($presetClient ?? null)
                <p class="text-success small">Выбран клиент: <strong>{{ $presetClient->full_name }}</strong> ({{ $presetClient->phone }})</p>
                @endif
                <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Имя / ФИО</label>
                    <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name', $presetClient?->full_name) }}" id="contact_name">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Телефон</label>
                    <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone', $presetClient?->phone) }}" id="contact_phone">
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header">Исход и заметки</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Исход</label>
                <select name="outcome" class="form-select">
                    <option value="">—</option>
                    @foreach(\App\Models\CallCenterContact::OUTCOMES as $k => $v)
                        <option value="{{ $k }}" {{ old('outcome') === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Заметки</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="{{ route('call-center.index') }}" class="btn btn-secondary">Отмена</a>
</form>

@push('scripts')
<script>
(function() {
    var clientSearch = document.getElementById('client_search');
    var clientId = document.getElementById('client_id');
    var contactName = document.getElementById('contact_name');
    var contactPhone = document.getElementById('contact_phone');
    var resultsDiv = document.getElementById('client_search_results');
    var searchTimer;
    @if($presetClient ?? null)
    clientId.value = '{{ $presetClient->id }}';
    contactName.value = {!! json_encode($presetClient->full_name) !!};
    contactPhone.value = {!! json_encode($presetClient->phone ?? '') !!};
    clientSearch.value = {!! json_encode($presetClient->full_name) !!};
    @endif
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
                            contactName.value = c.full_name || '';
                            contactPhone.value = c.phone || '';
                            clientSearch.value = c.full_name || '';
                            resultsDiv.style.display = 'none';
                        };
                        resultsDiv.appendChild(a);
                    });
                    resultsDiv.style.display = data.length ? 'block' : 'none';
                });
        }, 300);
    });
})();
</script>
@endpush
@endsection
