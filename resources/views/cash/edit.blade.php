@extends('layouts.app')

@section('title', 'Редактировать ' . $cashDocument->document_number)

@section('content')
<h1 class="h4 mb-4">Редактировать кассовый документ {{ $cashDocument->document_number }}</h1>

<form method="post" action="{{ route('cash.update', $cashDocument) }}">
    @csrf
    @method('PUT')
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Магазин *</label>
                    <select name="store_id" class="form-select" required>
                        @foreach($stores as $s)
                            <option value="{{ $s->id }}" {{ old('store_id', $cashDocument->store_id) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Дата *</label>
                    <input type="date" name="document_date" class="form-control" value="{{ old('document_date', \Carbon\Carbon::parse($cashDocument->document_date)->format('Y-m-d')) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Клиент</label>
                    <select name="client_id" class="form-select">
                        <option value="">— Без привязки к клиенту</option>
                        @foreach(\App\Models\Client::orderBy('full_name')->get(['id','full_name']) as $c)
                            <option value="{{ $c->id }}" {{ old('client_id', $cashDocument->client_id) == $c->id ? 'selected' : '' }}>{{ $c->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Вид операции *</label>
                    <select name="operation_type_id" id="operation_type_id" class="form-select" required>
                        <optgroup label="Приход">
                            @foreach($incomeTypes as $t)
                                <option value="{{ $t->id }}" {{ old('operation_type_id', $cashDocument->operation_type_id) == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="Расход">
                            @foreach($expenseTypes as $t)
                                <option value="{{ $t->id }}" {{ old('operation_type_id', $cashDocument->operation_type_id) == $t->id ? 'selected' : '' }}>{{ $t->name }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>
                <div class="col-12" id="target_store_wrap" style="{{ (old('operation_type_id', $cashDocument->operation_type_id) == $transferType?->id) ? '' : 'display:none' }}">
                    <label class="form-label">Касса назначения *</label>
                    <select name="target_store_id" id="target_store_id" class="form-select">
                        <option value="">— Выберите кассу</option>
                        @foreach($stores as $s)
                            <option value="{{ $s->id }}" {{ old('target_store_id', $cashDocument->target_store_id) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Деньги списываются из выбранной выше кассы и зачисляются в кассу назначения.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Сумма (₽) *</label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0.01" value="{{ old('amount', $cashDocument->amount) }}" placeholder="0.00" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Комментарий</label>
                    <textarea name="comment" class="form-control" rows="2" placeholder="Основание, примечание">{{ old('comment', $cashDocument->comment) }}</textarea>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="{{ route('cash.show', $cashDocument) }}" class="btn btn-secondary">Отмена</a>
</form>
@push('scripts')
<script>
(function() {
    var opSelect = document.getElementById('operation_type_id');
    var targetWrap = document.getElementById('target_store_wrap');
    var targetSelect = document.getElementById('target_store_id');
    var transferTypeId = {{ $transferType?->id ?? 0 }};
    function toggle() {
        var isTransfer = opSelect.value == transferTypeId;
        targetWrap.style.display = isTransfer ? 'block' : 'none';
        targetSelect.required = isTransfer;
    }
    opSelect.addEventListener('change', toggle);
    toggle();
})();
</script>
@endpush
@endsection
