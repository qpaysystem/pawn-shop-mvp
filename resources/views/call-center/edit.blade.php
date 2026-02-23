@extends('layouts.app')

@section('title', 'Редактировать обращение')

@section('content')
<h1 class="h4 mb-4">Редактировать обращение #{{ $callCenterContact->id }}</h1>

<form method="post" action="{{ route('call-center.update', $callCenterContact) }}">
    @csrf @method('PUT')
    <div class="card mb-3">
        <div class="card-header">Канал и направление</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Канал *</label>
                    <select name="channel" class="form-select" required>
                        @foreach(\App\Models\CallCenterContact::CHANNELS as $k => $v)
                            <option value="{{ $k }}" {{ old('channel', $callCenterContact->channel) === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Направление *</label>
                    <select name="direction" class="form-select" required>
                        <option value="incoming" {{ old('direction', $callCenterContact->direction) === 'incoming' ? 'selected' : '' }}>Входящее</option>
                        <option value="outgoing" {{ old('direction', $callCenterContact->direction) === 'outgoing' ? 'selected' : '' }}>Исходящее</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Магазин</label>
                    <select name="store_id" class="form-select">
                        <option value="">—</option>
                        @foreach($stores as $s)
                            <option value="{{ $s->id }}" {{ old('store_id', $callCenterContact->store_id) == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
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
                    <input type="date" name="contact_date" class="form-control" value="{{ old('contact_date', $callCenterContact->contact_date ? \Carbon\Carbon::parse($callCenterContact->contact_date)->format('Y-m-d') : '') }}" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Время</label>
                    <input type="time" name="contact_time" class="form-control" value="{{ old('contact_time', $callCenterContact->contact_date ? \Carbon\Carbon::parse($callCenterContact->contact_date)->format('H:i') : '') }}">
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header">Клиент / контакт</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Клиент</label>
                <select name="client_id" class="form-select" id="client_id">
                    <option value="">—</option>
                    @foreach(\App\Models\Client::orderBy('full_name')->get() as $c)
                        <option value="{{ $c->id }}" {{ old('client_id', $callCenterContact->client_id) == $c->id ? 'selected' : '' }}>{{ $c->full_name }} ({{ $c->phone }})</option>
                    @endforeach
                </select>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Имя / ФИО</label>
                    <input type="text" name="contact_name" class="form-control" value="{{ old('contact_name', $callCenterContact->contact_name) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Телефон</label>
                    <input type="text" name="contact_phone" class="form-control" value="{{ old('contact_phone', $callCenterContact->contact_phone) }}">
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header">Исход, сделка и заметки</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Исход</label>
                <select name="outcome" class="form-select">
                    <option value="">—</option>
                    @foreach(\App\Models\CallCenterContact::OUTCOMES as $k => $v)
                        <option value="{{ $k }}" {{ old('outcome', $callCenterContact->outcome) === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Договор залога</label>
                    <select name="pawn_contract_id" class="form-select">
                        <option value="">—</option>
                        @foreach($pawnContracts as $p)
                            <option value="{{ $p->id }}" {{ old('pawn_contract_id', $callCenterContact->pawn_contract_id) == $p->id ? 'selected' : '' }}>{{ $p->contract_number }} ({{ $p->client?->full_name }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Договор скупки</label>
                    <select name="purchase_contract_id" class="form-select">
                        <option value="">—</option>
                        @foreach($purchaseContracts as $p)
                            <option value="{{ $p->id }}" {{ old('purchase_contract_id', $callCenterContact->purchase_contract_id) == $p->id ? 'selected' : '' }}>{{ $p->contract_number }} ({{ $p->client?->full_name }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Договор комиссии</label>
                    <select name="commission_contract_id" class="form-select">
                        <option value="">—</option>
                        @foreach($commissionContracts as $c)
                            <option value="{{ $c->id }}" {{ old('commission_contract_id', $callCenterContact->commission_contract_id) == $c->id ? 'selected' : '' }}>{{ $c->contract_number }} ({{ $c->client?->full_name }})</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Заметки</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes', $callCenterContact->notes) }}</textarea>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-primary">Сохранить</button>
    <a href="{{ route('call-center.show', $callCenterContact) }}" class="btn btn-secondary">Отмена</a>
</form>
@endsection
